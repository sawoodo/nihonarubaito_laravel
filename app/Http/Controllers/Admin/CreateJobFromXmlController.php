<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Job;
use App\Models\Language;
use App\Models\User;
use App\Models\Area;
use App\Services\JobDeduplicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateJobFromXmlController extends Controller
{
    /**
     * Maps the translation prompt's short slugs to the SEO slugs stored in `tags`.
     * Slugs not listed here are looked up as-is.
     */
    private const TAG_SLUG_MAP = [
        // shift / time
        'early-morning'      => 'early-morning-jobs',
        'morning'            => 'morning-jobs',
        'daytime'            => 'daytime-jobs',
        'evening'            => 'evening-jobs',
        'night-shift'        => 'night-shift-jobs',
        // days / schedule
        'weekend'            => 'weekend-jobs',
        'weekdays-only'      => 'weekday-jobs',
        'flexible-shift'     => 'flexible-shift-jobs',
        'one-day-week'       => 'one-day-week-jobs',
        'four-plus-days'     => 'four-days-week-jobs',
        'short-term'         => 'short-term-jobs',
        'long-term'          => 'long-term-jobs',
        // payment
        'hand-cash'          => 'hand-cash-jobs',
        'daily-payment'      => 'daily-payment-jobs',
        'weekly-payment'     => 'weekly-payment-jobs',
        'advance-payment'    => 'advance-payment-jobs',
        'high-wage'          => 'high-wage-jobs',
        // audience
        'foreigners-welcome' => 'jobs-for-foreigners',
        'students-welcome'   => 'student-jobs',
        'no-experience'      => 'no-experience-jobs',
        'second-job'         => 'second-job',
        'homemakers-welcome' => 'jobs-for-homemakers',
        'freeters-welcome'   => 'freeter-jobs',
        'seniors-welcome'    => 'senior-jobs',
        'career-gap-ok'      => 'career-gap-ok-jobs',
        // conditions
        'near-station'       => 'near-station-jobs',
        'transport-paid'     => 'transport-paid-jobs',
        'training-provided'  => 'training-provided-jobs',
        'insurance'          => 'jobs-with-insurance',
        'uniform-provided'   => 'uniform-provided-jobs',
        'meals-provided'     => 'jobs-with-meals',
        'no-resume'          => 'no-resume-jobs',
        'appearance-free'    => 'appearance-free-jobs',
        // 'summer' has no prompt slug yet; summer-jobs stays manual
    ];

    public function create(Request $request)
    {
        $user = session('user');

        if ($request->isMethod('post')) {
            $rules = [
                'delete_at'  => 'required|integer|min:1',
                'xml_data'   => 'required|string',
                'apply_link' => 'required|string',
            ];

            if ($user->role_id === User::ROLE_ADMIN) {
                $rules['lang_id'] = 'required|integer|min:1';
            }

            $validated = $request->validate($rules);

            $xmlString = $this->cleanXmlInput($validated['xml_data']);

            try {
                $xml = simplexml_load_string($xmlString);
            } catch (\Exception $e) {
                return back()->withInput()->withErrors(['xml_data' => 'Invalid XML data: ' . $e->getMessage()]);
            }

            if ($xml === false) {
                return back()->withInput()->withErrors(['xml_data' => 'Invalid XML data.']);
            }

            $langId = ($user->role_id === User::ROLE_ADMIN) ? $validated['lang_id'] : ($user->lang_id ?? 1);
            $deleteInDays = max(1, (int) $validated['delete_at']);
            $applyLink = $validated['apply_link'];
            $imgLink = (string) $request->input('img_link', '');
            $featured = $request->has('featured') ? 1 : 0;
            $sendEmail = $request->has('send_email') ? 1 : 0;
            $userId = ($user->role_id === User::ROLE_ADMIN && (int) $request->input('user_id'))
                ? (int) $request->input('user_id') : $user->id;
            $imgId = (int) $request->input('images_img_id', 0);

            $jobData = $this->createJobArray(
                $xml, $langId, $featured, $sendEmail, $applyLink, $imgLink, $deleteInDays, $userId, $imgId
            );

            // Tier 0: apply_link check (highest priority — exact URL match)
            if (!$request->input('skip_duplicate_check')) {
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

                        // Duplicate check (skip if user confirmed)
            if (!$request->input('skip_duplicate_check')) {
                $dup = JobDeduplicator::findDuplicate(
                    $jobData['company_name'],
                    (int) $jobData['prefecture_id'],
                    (int) $jobData['area_id'],
                    $jobData['title']
                );
                if ($dup) {
                    $levelLabels = ['high' => 'HIGH', 'medium' => 'MEDIUM', 'low' => 'LOW'];
                    $statusLabels = [1 => 'Draft', 2 => 'Pending', 3 => 'Published'];
                    $dupJob = $dup['job'];
                    return back()->withInput()->with('duplicate_warning', [
                        'level'   => $dup['level'],
                        'label'   => $levelLabels[$dup['level']] ?? $dup['level'],
                        'job_no'  => $dupJob->job_no,
                        'title'   => $dupJob->title,
                        'date'    => $dupJob->date,
                        'status'  => $statusLabels[$dupJob->job_status_id] ?? 'Unknown',
                    ]);
                }
            }

            $job = Job::create($jobData);
            $job->update(['job_no' => (string) $job->id]);
            $this->attachTagsFromXml($xml, $job->id);

            return redirect("/admin/jobs/{$job->job_no}/edit")
                ->with('success', 'Job has been successfully created from XML.');
        }

        // GET - show form
        $advertiserList = User::where('role_id', User::ROLE_ADVERTISER)
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->first_name} {$u->last_name}"])
            ->prepend('Please select', 0)
            ->toArray();

        $data = [
            'activeSideMenu'  => 'jobs',
            'advertiser_list'  => $advertiserList,
            'images_img_id'    => 0,
            'images_img_name'  => '',
            'images_img_ext'   => '',
            'featured'         => false,
            'send_email'       => false,
        ];

        if ($user->role_id === User::ROLE_ADMIN) {
            $data['language_list'] = Language::pluck('english', 'id')->prepend('Please select', 0)->toArray();
            $data['role_id'] = $user->role_id;
        }

        return view('admin.jobs.create-from-xml', $data);
    }

    public function uploadFile(Request $request)
    {
        if (!$request->ajax()) {
            return response()->json(['err' => 'Invalid request.']);
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['err' => 'No file uploaded or upload error.']);
        }

        $file = $request->file('file');

        // Validate file type
        $allowedMimes = ['application/xml', 'text/xml'];
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'xml' && !in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json(['err' => 'Only XML files are allowed.']);
        }

        // Validate file size (2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json(['err' => 'File size must be less than 2MB.']);
        }

        try {
            $content = $this->cleanXmlInput(file_get_contents($file->getRealPath()));
            $xml = simplexml_load_string($content);

            if ($xml === false) {
                return response()->json(['err' => 'Invalid XML file.']);
            }

            $user = session('user');
            $jobs = [];
            $xmlObjects = []; // Preserve XML for tag parsing

            foreach ($xml->job as $jobXml) {
                $jobs[] = $this->createJobArray(
                    $jobXml,
                    1,           // lang_id = English
                    0,           // featured = false
                    0,           // send_email = false
                    '',          // apply_link = empty
                    '',          // img_link = empty
                    10,          // delete_at = 10 days
                    $user->id,   // user_id
                    0            // img_id
                );
                $xmlObjects[] = $jobXml; // Keep XML for tag attachment
            }

            if (empty($jobs)) {
                return response()->json(['err' => 'No job elements found in XML.']);
            }

            // Batch insert with duplicate detection
            $dupCount = 0;
            foreach ($jobs as $index => $jobData) {
                // Tier 0: apply_link check (exact URL match)
                $existingByUrl = JobDeduplicator::findByApplyLink($jobData['apply_link'] ?? '');
                if ($existingByUrl) {
                    $jobData['title'] = '[DUPLICATE URL] ' . $jobData['title'];
                    $jobData['job_status_id'] = Job::STATUS_DRAFT;
                    $dupCount++;
                    $job = Job::create($jobData);
                    $job->update(['job_no' => (string) $job->id]);
                    $this->attachTagsFromXml($xmlObjects[$index], $job->id);
                    continue;
                }

                $dup = JobDeduplicator::findDuplicate(
                    $jobData['company_name'],
                    (int) $jobData['prefecture_id'],
                    (int) $jobData['area_id'],
                    $jobData['title']
                );
                if ($dup) {
                    $jobData['title'] = '[POSSIBLE DUPLICATE] ' . $jobData['title'];
                    $jobData['job_status_id'] = Job::STATUS_DRAFT;
                    $dupCount++;
                }
                $job = Job::create($jobData);
                $job->update(['job_no' => (string) $job->id]);
                $this->attachTagsFromXml($xmlObjects[$index], $job->id);
            }

            $msg = count($jobs) . ' jobs created.';
            if ($dupCount > 0) {
                $msg .= " {$dupCount} flagged as possible duplicates (saved as Draft).";
            }

            return response()->json([
                'success' => true,
                'message' => 'XML processed successfully. ' . $msg,
            ]);
        } catch (\Exception $e) {
            return response()->json(['err' => 'Error processing XML: ' . $e->getMessage()]);
        }
    }

    private function createJobArray(
        \SimpleXMLElement $xml,
        int $langId,
        int $featured,
        int $sendEmail,
        string $applyLink,
        string $imgLink,
        int $deleteInDays,
        int $userId,
        int $imgId
    ): array {
        // Description + responsibilities
        $description = (string) ($xml->description ?? '');
        if (isset($xml->description->responsibilities)) {
            $items = [];
            foreach ($xml->description->responsibilities->item as $item) {
                $items[] = (string) $item;
            }
            if ($items) {
                $description .= '<br /><br />' . implode('<br />', $items);
            }
        }

        // Station lines
        $station = '';
        if (isset($xml->station->line)) {
            $lines = [];
            foreach ($xml->station->line as $line) {
                $lines[] = (string) $line;
            }
            $station = implode('<br />', $lines);
        } else {
            $station = (string) ($xml->station ?? '');
        }

        // Wage
        $wage = '';
        $wageDetail = '';
        if (isset($xml->wage->base)) {
            $wage = (string) $xml->wage->base;
            $wageItems = [];
            foreach ($xml->wage->children() as $child) {
                if ($child->getName() === 'base') {
                    continue; // base is stored separately in `wage` column — skip to avoid duplication
                }
                $incentive = trim((string) $child);
                if ($incentive !== '') {
                    $wageItems[] = $incentive;
                }
            }
            $wageDetail = implode('<br/>', $wageItems);
        } else {
            $wage = (string) ($xml->wage ?? '');
        }

        // Benefits
        $benefits = '';
        if (isset($xml->benefits->item)) {
            $items = [];
            foreach ($xml->benefits->item as $item) {
                $items[] = (string) $item;
            }
            $benefits = implode('<br />', $items);
        }

        // Requirements
        $requirement = '';
        if (isset($xml->requirement->item)) {
            $items = [];
            foreach ($xml->requirement->item as $item) {
                $items[] = (string) $item;
            }
            $requirement = implode('<br />', $items);
        } else {
            $requirement = (string) ($xml->requirement ?? '');
        }


        // Validate area_id resolves — a bad id silently vanishes the job via the
        // inner join in Job::scopeWithLocalizedNames (no listing, no 404). Log-only:
        // the job still imports; this surfaces the failure for later triage.
        $xmlAreaId = (int) ($xml->area_id ?? 0);
        if ($xmlAreaId > 0 && !Area::whereKey($xmlAreaId)->exists()) {
            Log::warning('XML import: unresolvable area_id', [
                'area_id'       => $xmlAreaId,
                'prefecture_id' => (int) ($xml->prefecture_id ?? 0),
                'address'       => (string) ($xml->address ?? ''),
                'title'         => mb_substr((string) ($xml->title ?? ''), 0, 80),
            ]);
        }
        return [
            'job_no'               => '',
            'title'                => (string) ($xml->title ?? ''),
            'company_name'         => (string) ($xml->company ?? ''),
            'description'          => $description,
            'job_category_id'      => (int) ($xml->job_category_id ?? 0),
            'prefecture_id'        => (int) ($xml->prefecture_id ?? 0),
            'area_id'              => $xmlAreaId,
            'station'              => $station,
            'address'              => (string) ($xml->address ?? ''),
            'japanese_level'       => (int) ($xml->japanese_level ?? 0),
            'working_hours'        => (string) ($xml->working_hours ?? ''),
            'working_days'         => (string) ($xml->working_days ?? ''),
            'wage'                 => $wage,
            'wage_type_id'         => (int) ($xml->wage_type_id ?? 0),
            'wage_detail'          => $wageDetail,
            'trans_exp_id'         => (int) ($xml->trans_exp_id ?? 0),
            'transportation_detail' => (string) ($xml->transportation_access ?? ''),
            'benefits'             => $benefits,
            'requirement'          => $requirement,
            'apply_link'           => $applyLink ?: (string) ($xml->apply_link ?? ''),
            'img_link'             => $imgLink,
            'img_path'             => '',
            'img_name'             => '',
            'img_ext'              => '',
            'img_id'               => $imgId,
            'featured'             => $featured,
            'send_email'           => $sendEmail,
            'delete_at'            => date('Y-m-d', strtotime("+{$deleteInDays} days")),
            'lang_id'              => $langId,
            'job_status_id'        => Job::STATUS_PENDING,
            'user_id'              => $userId,
        ];
    }

    /**
     * Attach tags parsed from the XML <tags> element to a newly created job.
     * NEVER throws — a tag failure must not break the job import.
     */
    private function attachTagsFromXml($xml, int $jobId): void
    {
        try {
            $raw = trim((string) ($xml->tags ?? ''));
            if ($raw === '') {
                return; // no tags element (old XML) — nothing to do, zero overhead
            }

            // slug => id, fetched once and cached (tags change almost never)
            $tagMap = \Illuminate\Support\Facades\Cache::remember(
                'tag_slug_id_map',
                3600,
                fn () => DB::table('tags')->pluck('id', 'slug')->toArray()
            );

            $unknown = [];
            $tagIds  = [];

            foreach (explode(',', $raw) as $slug) {
                $slug = trim($slug);
                if ($slug === '') {
                    continue;
                }
                // map short slug -> DB slug (or use as-is if not in the map)
                $dbSlug = self::TAG_SLUG_MAP[$slug] ?? $slug;

                if (isset($tagMap[$dbSlug])) {
                    $tagIds[$tagMap[$dbSlug]] = true; // dedupe via key
                } else {
                    $unknown[] = $slug;
                }
            }

            if (!empty($unknown)) {
                \Illuminate\Support\Facades\Log::warning('Unknown tag slugs in XML import', [
                    'job_id' => $jobId,
                    'slugs'  => $unknown,
                ]);
            }

            if (empty($tagIds)) {
                return;
            }

            // ONE batch insert. Composite PK (tag_id, job_id) makes duplicates a no-op.
            $rows = [];
            foreach (array_keys($tagIds) as $tagId) {
                $rows[] = ['job_id' => $jobId, 'tag_id' => $tagId];
            }
            DB::table('job_tag')->insertOrIgnore($rows);

        } catch (\Throwable $e) {
            // Tag failure must NEVER block the job import.
            \Illuminate\Support\Facades\Log::error('Tag attach failed (job still imported)', [
                'job_id' => $jobId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function cleanXmlInput(string $raw): string
    {
        $xml = trim($raw);
        $xml = preg_replace('/^```xml\s*/', '', $xml);            // Remove markdown code fence start
        $xml = preg_replace('/```\s*$/', '', $xml);                // Remove markdown code fence end
        $xml = html_entity_decode($xml);                           // Fix &lt; → <
        $xml = preg_replace('/^\x{FEFF}/u', '', $xml);            // Remove BOM
        $xml = preg_replace('/^[^<]*/', '', $xml, 1);             // Remove anything before first <
        // Sanitize bare & characters that aren't already XML entities
        $xml = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|#\d+;|#x[\da-fA-F]+;)/', '&amp;', $xml);
        return $xml;
    }
}
