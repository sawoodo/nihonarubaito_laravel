<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Category;
use App\Models\Image;
use App\Models\FbPost;
use App\Models\Job;
use App\Models\Language;
use App\Models\Prefecture;
use App\Models\TransExpPayment;
use App\Models\User;
use App\Models\WageType;
use App\Services\JobDeduplicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request, string $status = 'all', int $langId = 0, int $userId = 0)
    {
        $user = session('user');

        // Language/User filter form submits via POST
        if ($request->isMethod('post')) {
            $langId = (int) $request->input('lang_id', 0);
            $userId = (int) $request->input('user_id', 0);
        }

        $statusId = $this->getJobStatusId($status);
        $featured = ($status === 'featured');

        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $countQuery = Job::forBackend($user->id, $user->role_id, $statusId, $langId, $userId, $featured);
        $totalRecords = $countQuery->count();

        $jobs = Job::forBackend($user->id, $user->role_id, $statusId, $langId, $userId, $featured)
            ->skip($offset)
            ->take(self::PER_PAGE)
            ->get();

        $pagination = $this->buildAdminPagination(
            url("admin/jobs/{$status}/{$langId}/{$userId}"),
            $totalRecords,
            self::PER_PAGE,
            $page
        );

        // User list for filter dropdown (backend users)
        $backendUsers = User::whereIn('role_id', [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_ADVERTISER])
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->first_name} {$u->last_name}"])
            ->prepend('All Users', 0)
            ->toArray();

        // Language list for filter dropdown
        $languages = Language::pluck('english', 'id')->prepend('All Languages', 0)->toArray();

        return view('admin.jobs.index', [
            'jobs'          => $jobs,
            'pagination'    => $pagination,
            'total_records' => $totalRecords,
            'page_number'   => $page,
            'active_tab'    => $status,
            'job_status'    => $status,
            'lang_id'       => $langId,
            'user_id'       => $userId,
            'language_list' => $languages,
            'user_list'     => $backendUsers,
            'from'          => date('d/m/Y'),
            'to'            => date('d/m/Y'),
            'search'        => '',
            'activeSideMenu' => 'jobs',
        ]);
    }

    public function search(Request $request, string $status)
    {
        $user = session('user');
        $statusId = $this->getJobStatusId($status);

        $search = (string) ($request->input('search') ?? '');
        $fromRaw = $request->input('from', '');
        $toRaw = $request->input('to', '');

        // Convert dd/mm/yyyy (datepicker format) to Y-m-d (MySQL format)
        $fromSql = $this->toMysqlDate($fromRaw) ?? date('Y-m-d', strtotime('-30 days'));
        $toSql = $this->toMysqlDate($toRaw) ?? date('Y-m-d');

        $jobs = Job::searchForBackend($user->id, $user->role_id, $statusId, $search, $fromSql, $toSql)->get();

        $backendUsers = User::whereIn('role_id', [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_ADVERTISER])
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->first_name} {$u->last_name}"])
            ->prepend('All Users', 0)
            ->toArray();

        $languages = Language::pluck('english', 'id')->prepend('All Languages', 0)->toArray();

        return view('admin.jobs.index', [
            'jobs'          => $jobs,
            'pagination'    => '',
            'total_records' => count($jobs),
            'page_number'   => 1,
            'active_tab'    => $status,
            'job_status'    => $status,
            'lang_id'       => 0,
            'user_id'       => 0,
            'language_list' => $languages,
            'user_list'     => $backendUsers,
            'from'          => $fromRaw,
            'to'            => $toRaw,
            'search'        => $search,
            'activeSideMenu' => 'jobs',
        ]);
    }

    public function create(Request $request)
    {
        $user = session('user');

        if ($request->isMethod('post')) {
            $rules = [
                'title'           => 'required|string',
                'company_name'    => 'required|string',
                'description'     => 'required|string',
                'job_category_id' => 'required|integer|min:1',
                'prefecture_id'   => 'required|integer|min:1',
                'area_id'         => 'required|integer|min:1',
                'station'         => 'required|string',
                'address'         => 'required|string',
                'japanese_level'  => 'required|integer|min:1',
                'working_hours'   => 'required|string',
                'working_days'    => 'required|string',
                'wage'            => 'required|string',
                'wage_type_id'    => 'required|integer|min:1',
                'trans_exp_id'    => 'required|integer|min:1',
                'requirement'     => 'required|string',
            ];

            if ($user->role_id === User::ROLE_ADMIN) {
                $rules['lang_id'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            // Tier 0: apply_link check (highest priority — exact URL match)
            if (!$request->input('skip_duplicate_check')) {
                $applyLink = trim((string) $request->input('apply_link', ''));
                $existingByUrl = JobDeduplicator::findByApplyLink($applyLink);
                if ($existingByUrl) {
                    return back()->withInput()->with('duplicate_warning', [
                        'level'   => 'high',
                        'label'   => 'EXACT URL MATCH',
                        'job_no'  => $existingByUrl->job_no,
                        'title'   => $existingByUrl->title,
                        'date'    => $existingByUrl->date,
                        'status'  => $existingByUrl->job_status_id == 3 ? 'Published' : 'Pending',
                    ]);
                }
            }

                        // Duplicate check (skip if user confirmed via hidden field)
            if (!$request->input('skip_duplicate_check')) {
                $dup = JobDeduplicator::findDuplicate(
                    $validated['company_name'],
                    (int) $validated['prefecture_id'],
                    (int) $validated['area_id'],
                    $validated['title']
                );
                if ($dup) {
                    $levelLabels = ['high' => 'HIGH', 'medium' => 'MEDIUM', 'low' => 'LOW'];
                    $statusLabels = [1 => 'Draft', 2 => 'Pending', 3 => 'Published'];
                    $dupJob = $dup['job'];
                    $dupWarning = [
                        'level'   => $dup['level'],
                        'label'   => $levelLabels[$dup['level']] ?? $dup['level'],
                        'job_no'  => $dupJob->job_no,
                        'title'   => $dupJob->title,
                        'date'    => $dupJob->date,
                        'status'  => $statusLabels[$dupJob->job_status_id] ?? 'Unknown',
                    ];
                    return back()->withInput()->with('duplicate_warning', $dupWarning);
                }
            }

            $deleteInDays = max(1, (int) $request->input('delete_at', 60));

            $job = Job::create([
                'job_no'          => '',
                'title'           => $validated['title'],
                'company_name'    => $validated['company_name'],
                'description'     => $validated['description'],
                'job_category_id' => $validated['job_category_id'],
                'prefecture_id'   => $validated['prefecture_id'],
                'area_id'         => $validated['area_id'],
                'station'         => $validated['station'],
                'address'         => $validated['address'],
                'japanese_level'  => $validated['japanese_level'],
                'working_hours'   => $validated['working_hours'],
                'working_days'    => $validated['working_days'],
                'wage'            => $validated['wage'],
                'wage_type_id'    => $validated['wage_type_id'],
                'trans_exp_id'    => $validated['trans_exp_id'],
                'requirement'     => $validated['requirement'],
                'apply_link'      => (string) $request->input('apply_link', ''),
                'img_link'        => (string) $request->input('img_link', ''),
                'img_path'        => '',
                'img_name'        => '',
                'img_ext'         => '',
                'img_id'          => (int) $request->input('images_img_id', 0),
                'featured'        => $request->has('featured') ? 1 : 0,
                'send_email'      => $request->has('send_email') ? 1 : 0,
                'delete_at'       => date('Y-m-d', strtotime("+{$deleteInDays} days")),
                'lang_id'         => ($user->role_id === User::ROLE_ADMIN) ? $validated['lang_id'] : $user->lang_id,
                'user_id'         => ($user->role_id === User::ROLE_ADMIN && (int) $request->input('user_id'))
                                     ? (int) $request->input('user_id') : $user->id,
                'job_status_id'   => Job::STATUS_PENDING,
            ]);

            // Set job_no = id (string representation of auto-increment)
            $job->update(['job_no' => (string) $job->id]);

            return redirect('/admin/jobs')->with('success', 'Job has been successfully created.');
        }

        $dropdowns = $this->getFormDropdowns($user);

        // Pre-fill from query params (e.g., from Demand vs Supply page)
        $prefillPrefecture = (int) $request->query('prefecture_id');
        $prefillArea = (int) $request->query('area_id');
        $prefillCategory = (int) $request->query('category_id');

        if ($prefillPrefecture) {
            $dropdowns['area_list'] = Area::join('towns', 'areas.town_id', '=', 'towns.id')
                ->where('towns.prefecture_id', $prefillPrefecture)
                ->pluck('areas.english', 'areas.id')
                ->prepend('Please select', 0)
                ->toArray();
        }

        return view('admin.jobs.create', array_merge($dropdowns, [
            'job'              => null,
            'images_img_id'    => 0,
            'images_img_name'  => '',
            'images_img_ext'   => '',
            'featured'         => false,
            'send_email'       => false,
            'activeSideMenu'   => 'jobs',
            'prefill_prefecture_id' => $prefillPrefecture,
            'prefill_area_id'       => $prefillArea,
            'prefill_category_id'   => $prefillCategory,
        ]));
    }

    public function edit(Request $request, string $jobNo)
    {
        $user = session('user');
        $job = Job::forEdit($jobNo)->first();

        if (!$job) {
            return redirect('/admin/jobs')->with('error', 'Job not found.');
        }

        // Non-admins can't edit published jobs
        if ($user->role_id !== User::ROLE_ADMIN && $job->job_status_id === Job::STATUS_PUBLISHED) {
            return redirect('/admin/jobs')->with('error', 'Published jobs can only be edited by admins.');
        }

        if ($request->isMethod('post')) {
            $rules = [
                'title'           => 'required|string',
                'company_name'    => 'required|string',
                'description'     => 'required|string',
                'job_category_id' => 'required|integer|min:1',
                'prefecture_id'   => 'required|integer|min:1',
                'area_id'         => 'required|integer|min:1',
                'station'         => 'required|string',
                'address'         => 'required|string',
                'japanese_level'  => 'required|integer|min:1',
                'working_hours'   => 'required|string',
                'working_days'    => 'required|string',
                'wage'            => 'required|string',
                'wage_type_id'    => 'required|integer|min:1',
                'trans_exp_id'    => 'required|integer|min:1',
                'requirement'     => 'required|string',
            ];

            $validated = $request->validate($rules);

            $deleteInDays = max(1, (int) $request->input('delete_at', 60));

            $updateData = [
                'title'              => $validated['title'],
                'company_name'       => $validated['company_name'],
                'description'        => $validated['description'],
                'job_category_id'    => $validated['job_category_id'],
                'prefecture_id'      => $validated['prefecture_id'],
                'area_id'            => $validated['area_id'],
                'station'            => $validated['station'],
                'address'            => $validated['address'],
                'japanese_level'     => $validated['japanese_level'],
                'working_hours'      => $validated['working_hours'],
                'working_days'       => $validated['working_days'],
                'wage'               => $validated['wage'],
                'wage_type_id'       => $validated['wage_type_id'],
                'wage_detail'        => (string) $request->input('wage_detail', ''),
                'trans_exp_id'       => $validated['trans_exp_id'],
                'transportation_detail' => (string) $request->input('transportation_detail', ''),
                'benefits'           => (string) $request->input('benefits', ''),
                'requirement'        => $validated['requirement'],
                'apply_link'         => (string) $request->input('apply_link', ''),
                'img_link'           => (string) $request->input('img_link', ''),
                'img_id'             => (int) $request->input('images_img_id', 0),
                'featured'           => $request->has('featured') ? 1 : 0,
                'send_email'         => $request->has('send_email') ? 1 : 0,
                'delete_at'          => date('Y-m-d', strtotime("+{$deleteInDays} days")),
                'updated_by'         => $user->id,
            ];

            if ($user->role_id === User::ROLE_ADMIN) {
                $updateData['lang_id'] = $request->input('lang_id', $job->lang_id);
                if ((int) $request->input('user_id')) {
                    $updateData['user_id'] = (int) $request->input('user_id');
                }
            }

            Job::where('id', $job->id)->where('job_no', $jobNo)->update($updateData);

            // Update & Publish
            if ($request->input('update_and_publish')) {
                $freshJob = Job::where('job_no', $jobNo)->first();
                $this->createFbPost($freshJob);
                Job::where('job_no', $jobNo)->update(['job_status_id' => Job::STATUS_PUBLISHED]);
                return redirect('/admin/jobs')->with('success', 'Job has been updated and published.');
            }

            return redirect('/admin/jobs')->with('success', 'Job has been updated successfully.');
        }

        // Load areas for the prefecture
        $areas = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->where('t.prefecture_id', $job->prefecture_id)
            ->pluck('a.english', 'a.id')
            ->prepend('Please select area', 0)
            ->toArray();

        return view('admin.jobs.edit', array_merge($this->getFormDropdowns($user), [
            'job'             => $job,
            'area_list'       => $areas,
            'images_img_id'   => $job->images_img_id ?? 0,
            'images_img_name' => $job->images_img_name ?? '',
            'images_img_ext'  => $job->images_img_ext ?? '',
            'featured'        => (bool) $job->featured,
            'send_email'      => (bool) $job->send_email,
            'activeSideMenu'  => 'jobs',
        ]));
    }

    public function view(string $jobNo)
    {
        $job = Job::forView($jobNo)->first();

        if (!$job) {
            return redirect('/admin/jobs')->with('error', 'Job not found.');
        }

        return view('admin.jobs.view', [
            'job' => $job,
            'activeSideMenu' => 'jobs',
        ]);
    }

    public function cloneJob(string $jobNo)
    {
        $original = Job::where('job_no', $jobNo)->first();

        if (!$original) {
            return redirect('/admin/jobs')->with('error', 'Job not found.');
        }

        $user = session('user');

        $clone = Job::create([
            'job_no'          => '',
            'title'           => $original->title,
            'company_name'    => $original->company_name,
            'description'     => $original->description,
            'job_category_id' => $original->job_category_id,
            'prefecture_id'   => $original->prefecture_id,
            'area_id'         => $original->area_id,
            'station'         => $original->station,
            'address'         => $original->address,
            'japanese_level'  => $original->japanese_level,
            'working_hours'   => $original->working_hours,
            'working_days'    => $original->working_days,
            'wage'            => $original->wage,
            'wage_type_id'    => $original->wage_type_id,
            'wage_detail'     => $original->wage_detail,
            'trans_exp_id'    => $original->trans_exp_id,
            'transportation_detail' => $original->transportation_detail,
            'benefits'        => $original->benefits,
            'requirement'     => $original->requirement,
            'apply_link'      => $original->apply_link ?? '',
            'img_link'        => $original->img_link ?? '',
            'img_path'        => $original->img_path ?? '',
            'img_name'        => $original->img_name ?? '',
            'img_ext'         => $original->img_ext ?? '',
            'img_id'          => $original->img_id ?? 0,
            'featured'        => 0,
            'send_email'      => 0,
            'lang_id'         => $original->lang_id,
            'user_id'         => $user->id,
            'job_status_id'   => Job::STATUS_PENDING,
            'delete_at'       => date('Y-m-d', strtotime('+60 days')),
        ]);

        $clone->update(['job_no' => (string) $clone->id]);

        return redirect('/admin/jobs')->with('success', 'Job has been cloned successfully.');
    }

    public function publish(string $jobNo)
    {
        $user = session('user');
        if ($user->role_id !== User::ROLE_ADMIN) {
            return redirect('/admin/jobs')->with('error', 'Only admins can publish jobs.');
        }

        $job = Job::where('job_no', $jobNo)->first();
        if (!$job) {
            return redirect('/admin/jobs')->with('error', 'Job not found.');
        }
        if (!$job->area_id || $job->area_id == 0) {
            return redirect('/admin/jobs')->with('error', 'Please set the area before publishing.');
        }

        // Re-check apply_link duplicates at publish time
        if (!empty($job->apply_link) && $job->apply_link !== '123') {
            $existingPublished = DB::table('jobs')
                ->where('apply_link', $job->apply_link)
                ->where('job_status_id', Job::STATUS_PUBLISHED)
                ->where('id', '!=', $job->id)
                ->first();

            if ($existingPublished) {
                return redirect('/admin/jobs')->with('error',
                    'Cannot publish: A job with the same apply URL is already published as Job #' .
                    $existingPublished->job_no . '. Trash the existing one first or trash this draft.'
                );
            }
        }

        $this->createFbPost($job);
        $job->update(['job_status_id' => Job::STATUS_PUBLISHED]);

        return redirect('/admin/jobs')->with('success', 'Job has been published.');
    }

    private function createFbPost($job): void
    {
        // Only English jobs get Facebook posts (lang_id = 1)
        if ((int) $job->lang_id !== 1) {
            return;
        }

        $applyLink = url($job->detail_path) . '?utm_source=fb';
        $desc = strip_tags(str_replace(['<br/>', '<br>', '<br />'], ' ', $job->description ?? ''));

        $content = $job->title . "\n\n"
            . $desc . "\n\n"
            . ($job->station ? $job->station . "\n" : '')
            . ($job->working_hours ? $job->working_hours . "\n" : '')
            . ($job->working_days ? $job->working_days . "\n" : '')
            . "\n"
            . 'Check Job Detail: ' . $applyLink;

        // Schedule: 15 min after last post, or 3 hours ago if no recent posts
        $lastPost = FbPost::orderByDesc('id')->first();
        $now = now();

        if ($lastPost && $now->diffInDays($lastPost->created_at) === 0) {
            $scheduledAt = \Carbon\Carbon::parse($lastPost->scheduled_at)->addMinutes(15)->format('Y-m-d H:i:s');
        } else {
            $scheduledAt = $now->subHours(3)->format('Y-m-d H:i:s');
        }

        FbPost::create([
            'content'      => $content,
            'lang_id'      => $job->lang_id,
            'link'         => $applyLink,
            'published'    => false,
            'created_at'   => date('Y-m-d H:i:s'),
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function draft(string $jobNo)
    {
        Job::where('job_no', $jobNo)->update(['job_status_id' => Job::STATUS_PENDING]);
        return redirect('/admin/jobs')->with('success', 'Job has been set to draft.');
    }

    public function expire(string $jobNo)
    {
        Job::where('job_no', $jobNo)->update([
            'job_status_id' => Job::STATUS_EXPIRED,
            'Expire_Date'   => now(),
        ]);
        return redirect('/admin/jobs')->with('success', 'Job has been expired.');
    }

    public function trash(string $jobNo)
    {
        Job::where('job_no', $jobNo)->update(['job_status_id' => Job::STATUS_TRASHED]);
        return redirect('/admin/jobs')->with('success', 'Job has been trashed.');
    }

    public function toggleFeatured(Request $request, string $jobNo)
    {
        $featured = (int) $request->query('featured', 0);
        Job::where('job_no', $jobNo)->update(['featured' => $featured]);
        return redirect()->back()->with('success', $featured ? 'Job has been featured.' : 'Job has been unfeatured.');
    }

    public function attachImage(Request $request)
    {
        $jobId = (int) $request->input('job_id');
        $jobNo = $request->input('job_no');
        $imageId = (int) $request->input('image_id');

        $image = Image::find($imageId);
        if (!$image) {
            return response()->json(['status' => 'error', 'message' => 'Image not found']);
        }

        Job::where('id', $jobId)->where('job_no', $jobNo)->update(['img_id' => $imageId]);

        return response()->json(['status' => 'ok', 'data' => $image]);
    }

    public function detachImage(Request $request)
    {
        $jobId = (int) $request->input('job_id');
        $jobNo = $request->input('job_no');

        Job::where('id', $jobId)->where('job_no', $jobNo)->update([
            'img_id' => 0,
            'img_path' => '',
            'img_name' => '',
            'img_ext' => '',
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function getAreas(Request $request)
    {
        $prefectureId = (int) $request->input('prefecture_id', 0);

        if (!$prefectureId) {
            return response()->json(['status' => 'error']);
        }

        $areas = DB::table('areas as a')
            ->join('towns as t', 'a.town_id', '=', 't.id')
            ->where('t.prefecture_id', $prefectureId)
            ->select('a.id', 'a.english as name')
            ->orderBy('a.id')
            ->get();

        $formatted = $areas->map(fn($a) => [$a->id, $a->name])->values()->toJson();

        return response()->json(['status' => 'ok', 'areas' => $formatted]);
    }

    public function unfeaturing()
    {
        return view('admin.jobs.unfeaturing', [
            'activeSideMenu' => 'jobs',
        ]);
    }

    public function unfeature()
    {
        $affected = Job::where('featured', true)
            ->whereRaw('DATE(updated_at) < CURDATE()')
            ->update(['featured' => false]);

        return redirect('/admin/jobs')->with('success', "All featured jobs from yesterday have been marked unfeatured. ({$affected} jobs affected)");
    }

    // ── Private Helpers ──

    private function getJobStatusId(string $status): int
    {
        return match ($status) {
            'draft'     => Job::STATUS_PENDING,
            'published' => Job::STATUS_PUBLISHED,
            'expired'   => Job::STATUS_EXPIRED,
            'trashed'   => Job::STATUS_TRASHED,
            default     => 0,
        };
    }

    private function getFormDropdowns(User $user): array
    {
        $categories = Category::pluck('english', 'id')->prepend('Please select', 0)->toArray();
        $prefectures = Prefecture::pluck('english', 'id')->prepend('Please select', 0)->toArray();
        $wageTypes = WageType::pluck('english', 'id')->prepend('Please select', 0)->toArray();
        $transExp = TransExpPayment::pluck('english', 'id')->prepend('Please select', 0)->toArray();

        $japLevels = [0 => 'Please Select', 1 => 'N1', 2 => 'N2', 3 => 'N3', 4 => 'N4', 5 => 'N5'];

        $advertisers = User::where('role_id', User::ROLE_ADVERTISER)
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->first_name} {$u->last_name}"])
            ->prepend('Please select', 0)
            ->toArray();

        $data = [
            'job_cat_list'     => $categories,
            'prefecture_list'  => $prefectures,
            'area_list'        => [0 => 'Please select prefecture first'],
            'jap_level_list'   => $japLevels,
            'wage_type_list'   => $wageTypes,
            'trans_exp_list'   => $transExp,
            'advertiser_list'  => $advertisers,
        ];

        if ($user->role_id === User::ROLE_ADMIN) {
            $data['language_list'] = Language::pluck('english', 'id')->prepend('Please select', 0)->toArray();
        }

        return $data;
    }

    private function buildAdminPagination(string $baseUrl, int $totalRows, int $perPage, int $currentPage): string
    {
        $totalPages = (int) ceil($totalRows / $perPage);
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<ul class="pagination pagination-sm">';

        // First page
        if ($currentPage > 1) {
            $html .= '<li><a href="' . $baseUrl . '?page=1"><span class="glyphicon glyphicon-step-backward"></span></a></li>';
        }

        // Page numbers
        $start = max(1, $currentPage - 3);
        $end = min($totalPages, $currentPage + 3);

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $html .= '<li><a href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
            }
        }

        // Last page
        if ($currentPage < $totalPages) {
            $html .= '<li><a href="' . $baseUrl . '?page=' . $totalPages . '"><span class="glyphicon glyphicon-step-forward"></span></a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Convert dd/mm/yyyy or dd-mm-yyyy to Y-m-d. Returns null on invalid input.
     * Replicates CI3's mysql_date() helper.
     */
    private function toMysqlDate(?string $date): ?string
    {
        if (!$date || $date === '') {
            return null;
        }
        // Already Y-m-d?
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        // Replace slashes with dashes so strtotime interprets dd-mm-yyyy (European)
        $normalized = str_replace('/', '-', $date);
        $ts = strtotime($normalized);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
