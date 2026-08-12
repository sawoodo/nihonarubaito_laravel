<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Job;
use App\Models\Language;
use App\Models\Prefecture;
use App\Models\SecondaryApply;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class JobController extends Controller
{
    public function detail(Request $request, string $jobNo, string $slug = null)
    {
        // ?apply=true → redirect to apply page (CI3 compatibility)
        if ($request->query('apply') === 'true') {
            return redirect("jobs/{$jobNo}/apply");
        }

        // Get job's lang_id and status early (before language redirect)
        $jobBasic = Job::where('job_no', $jobNo)->select('lang_id', 'job_status_id')->first();

        if (!$jobBasic) {
            abort(404);
        }

        // Pending/Draft → 404 (check before language redirect to avoid loops)
        if (in_array((int) $jobBasic->job_status_id, [Job::STATUS_PENDING, Job::STATUS_DRAFT])) {
            abort(404);
        }

        // Expired/Trashed → 3-tier lifecycle (check before language redirect to avoid loops)
        if (in_array((int) $jobBasic->job_status_id, [Job::STATUS_EXPIRED, Job::STATUS_TRASHED])) {
            return $this->handleExpiredJob($jobNo);
        }

        // If user's session lang differs from job's lang, switch and redirect
        $currentLangId = session('user_lang', 1);
        if ((int) $currentLangId !== (int) $jobBasic->lang_id) {
            $lang = Language::find($jobBasic->lang_id);
            if ($lang) {
                session([
                    'user_lang' => $jobBasic->lang_id,
                    'lang_name' => strtolower($lang->english),
                ]);
            }
            return redirect($request->url());
        }

        $langName = session('lang_name', 'english');

        // Fetch job with localized names (replicates CI3 get_for_frontend join)
        $job = Job::withLocalizedNames($langName)
            ->where('jobs.job_no', $jobNo)
            ->where('jobs.lang_id', $currentLangId)
            ->first();

        if (!$job) {
            abort(404);
        }

        // Fetch 10 related jobs (same prefecture, published, exclude current, newest first)
        $relatedJobs = Job::withLocalizedNames($langName)
            ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
            ->where('jobs.prefecture_id', $job->prefecture_id)
            ->where('jobs.id', '!=', $job->id)
            ->orderBy('jobs.id', 'desc')
            ->limit(10)
            ->get();

        // Active job (status 3) or Quota Full (status 6) → 200
        $jobSlug = Str::slug(strtolower("{$langName}-{$job->title}"));

        // 301 redirect bare /jobs/{id}/detail → /jobs/{id}/detail/{slug}
        // Preserves query strings (utm_source=fb etc) for GA4 tracking
        if (!$slug || $slug !== $jobSlug) {
            $queryString = request()->getQueryString();
            $redirectUrl = url("jobs/{$jobNo}/detail/{$jobSlug}");
            if ($queryString) {
                $redirectUrl .= '?' . $queryString;
            }
            return redirect($redirectUrl, 301);
        }
        $breadcrumbData = $this->createBreadcrumb($job, $langName);

        // Transform title: location-first for distinctiveness in search results
        $t = $job->title;

        // Protect the wage segment (preserve 円 and 〜 — familiar to residents)
        $wage = '';
        if (preg_match('/[\d,]+円(?:[〜～][\d,]+円)?/u', $t, $w)) {
            $wage = $w[0];
            $t = str_replace($wage, '@@W@@', $t);
        }

        // Strip kanji station names (redundant — romaji is present)
        $t = preg_replace('/[\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]+/u', '', $t);

        // Restore the wage with native formatting intact
        $t = str_replace('@@W@@', $wage, $t);

        $t = str_replace(' Part Time Job', '', $t);
        $t = preg_replace('/\s+/', ' ', trim($t));

        // Split on the LAST " at " — role before, location after
        if (preg_match('/^(.+) at (.+)$/u', $t, $m)) {
            $pageTitle = trim($m[2]) . ' — ' . trim($m[1]) . ' | Nihon Arubaito';
        } else {
            $pageTitle = $t . ' | Nihon Arubaito';
        }

        return view('jobs.detail', [
            'job' => $job,
            'related_jobs' => $relatedJobs,
            'page_title' => $pageTitle,
            'page_description' => $job->description,
            'og_title' => $job->title,
            'og_description' => $job->description,
            'og_image' => ($job->images_img_name && $job->images_img_ext)
                ? url("frontend/images/jobs/{$job->images_img_name}{$job->images_img_ext}")
                : url('frontend/images/og-default.png'),
            'og_url' => url("jobs/{$jobNo}/detail/{$jobSlug}"),
            'canonical' => url("jobs/{$jobNo}/detail/{$jobSlug}"),
            'keywords' => "Part time jobs in {$job->area_name} {$job->prefecture_name} Japan, jobs listings japan, jobs Opportunities japan, Nihon Arubaito, Baito, Part-time job for foreigners",
            'schema_script' => $this->createSchema($job),
            'breadcrumb' => $breadcrumbData['html'],
            'breadcrumbItems' => $breadcrumbData['items'],
            'active_nav' => 'jobs',
            'load_value_commerce' => true,
        ]);
    }

    public function applySecondary(Request $request, string $jobNo)
    {
        // Check job exists and is active before showing apply form
        $jobBasic = Job::where('job_no', $jobNo)->select('job_status_id')->first();
        if (!$jobBasic) {
            abort(404);
        }
        if (in_array((int) $jobBasic->job_status_id, [Job::STATUS_EXPIRED, Job::STATUS_TRASHED])) {
            return redirect("jobs/{$jobNo}/detail", 301);
        }

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required|email',
                'phone' => 'required',
            ]);

            $job = Job::where('job_no', $jobNo)->first();

            if ($job) {
                // Send job link email
                $applyLink = $job->apply_link;
                $jobTitle = $job->title;
                $email = $validated['email'];

                Mail::send('emails.job-link', [
                    'apply_link' => $applyLink,
                    'job_title' => $jobTitle,
                ], function ($message) use ($email, $jobTitle) {
                    $message->to($email)
                        ->subject("Job Link: {$jobTitle}");
                });

                // Save secondary apply record
                SecondaryApply::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'job_no' => $jobNo,
                    'apply_date' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            return redirect('job/link-sent');
        }

        return view('jobs.apply-secondary', [
            'job_no' => $jobNo,
        ]);
    }

    public function linkSent()
    {
        return view('jobs.link-sent');
    }

    /**
     * 3-tier expired job lifecycle:
     *   Tier 1 (0-90 days):   200 + noindex + related jobs
     *   Tier 2 (91-365 days): 301 redirect to area/prefecture page
     *   Tier 3 (365+ days):   410 Gone
     */
    private function handleExpiredJob(string $jobNo)
    {
        $langName = session('lang_name', 'english');
        $job = Job::withLocalizedNames($langName)
            ->where('jobs.job_no', $jobNo)
            ->first();

        if (!$job) {
            abort(410);
        }

        // Calculate days since expiration: prefer Expire_Date, fallback to updated_at, then date
        // Use startOfDay() to match SQL DATEDIFF behavior (count calendar day boundaries)
        $expiredDate = $job->Expire_Date ?? $job->updated_at ?? $job->date;
        $daysSinceExpired = $expiredDate
            ? (int) abs(now()->startOfDay()->diffInDays($expiredDate->copy()->startOfDay()))
            : 999;

        // TIER 1: Recently expired (0-90 days) → 200 + noindex + related jobs
        if ($daysSinceExpired <= 90) {
            $relatedJobs = Job::withLocalizedNames($langName)
                ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
                ->where('jobs.prefecture_id', $job->prefecture_id)
                ->where('jobs.id', '!=', $job->id)
                ->orderBy('jobs.id', 'desc')
                ->limit(8)
                ->get();

            // If not enough from same prefecture, fill with any active jobs
            if ($relatedJobs->count() < 4) {
                $excludeIds = $relatedJobs->pluck('id')->push($job->id)->toArray();
                $moreJobs = Job::withLocalizedNames($langName)
                    ->where('jobs.job_status_id', Job::STATUS_PUBLISHED)
                    ->whereNotIn('jobs.id', $excludeIds)
                    ->orderBy('jobs.id', 'desc')
                    ->limit(8 - $relatedJobs->count())
                    ->get();
                $relatedJobs = $relatedJobs->merge($moreJobs);
            }

            $prefectureName = $job->prefecture_name ?? '';
            $prefectureSlug = $prefectureName ? strtolower(str_replace(' ', '-', $prefectureName)) : '';

            return response()
                ->view('jobs.expired', [
                    'job' => $job,
                    'related_jobs' => $relatedJobs,
                    'page_title' => 'Job Expired | Nihon Arubaito',
                    'prefecture_name' => $prefectureName,
                    'prefecture_slug' => $prefectureSlug,
                    'noindex' => true,
                ], 200);
        }

        // TIER 2: Old expired (91-365 days) → 301 redirect to area/prefecture page
        if ($daysSinceExpired <= 365) {
            // Try area page first
            if ($job->area_id) {
                $area = Area::find($job->area_id);
                if ($area) {
                    return redirect('/part-time-jobs-in-' . $area->slug, 301);
                }
            }
            // Fallback to prefecture page
            if ($job->prefecture_id) {
                $prefecture = Prefecture::find($job->prefecture_id);
                if ($prefecture) {
                    return redirect('/part-time-jobs-in-' . $prefecture->slug, 301);
                }
            }
            // Ultimate fallback
            return redirect('/', 301);
        }

        // TIER 3: Very old (365+ days) → 410 Gone
        abort(410);
    }

    private function createSchema(Job $job): string
    {
        // No schema for non-active jobs
        if (in_array((int) $job->job_status_id, [Job::STATUS_PENDING, Job::STATUS_EXPIRED, Job::STATUS_TRASHED])) {
            return '';
        }

        $datePosted = $job->date ? $job->date->format('Y-m-d') : '';
        $validThrough = $job->delete_at ? $job->delete_at->format('Y-m-d\TH:i') : '';
        $description = htmlentities(strip_tags($job->description ?? ''), ENT_QUOTES, 'UTF-8');

        $address = [
            '@type' => 'PostalAddress',
            'streetAddress' => $job->address ?? '',
            'addressLocality' => $job->area_name,
            'addressRegion' => $job->prefecture_name,
            'addressCountry' => 'JP',
        ];
        if (!empty($job->area_postal_code)) {
            $address['postalCode'] = $job->area_postal_code;
        }

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Nihon Arubaito',
                'value' => $job->job_no,
            ],
            'title' => $job->title,
            'description' => $description,
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => $job->company_name,
            ],
            'industry' => $job->category_name,
            'employmentType' => 'PART_TIME',
            'datePosted' => $datePosted,
            'validThrough' => $validThrough,
            'jobLocation' => [
                '@type' => 'Place',
                'address' => $address,
            ],
            'applicantLocationRequirements' => [
                '@type' => 'Country',
                'name' => 'Japan',
            ],
        ];

        // Add baseSalary only if parseable (shared static parser from Job model)
        $baseSalaryValue = Job::parseBaseSalaryLd($job->wage);
        if ($baseSalaryValue) {
            $schema['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => 'JPY',
                'value' => $baseSalaryValue,
            ];
        }

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "<script type=\"application/ld+json\">\n{$json}\n</script>";
    }

    private function createBreadcrumb(Job $job, string $langName): array
    {
        $prefectureName = $job->prefecture_name ?? '';
        $areaName = $job->area_name ?? '';
        $slug = Str::slug(strtolower("{$langName}-{$job->title}"));

        $homeUrl = url('/');

        // Search-based URLs for visual breadcrumb links (match CI3)
        $prefSearchUrl = url("jobs/search?query=&prefecture_id={$job->prefecture_id}&area_id=0");
        $areaSearchUrl = url("jobs/search?query=&prefecture_id={$job->prefecture_id}&area_id={$job->area_id}");

        $html = '<ol class="breadcrumb tw-mt-8">'
            . '<li><a href="' . e($homeUrl) . '">Home</a></li>'
            . '<li><a href="' . e($prefSearchUrl) . '">' . e($prefectureName) . '</a></li>'
            . '<li><a href="' . e($areaSearchUrl) . '">' . e($areaName) . '</a></li>'
            . '<li class="active">Job No. ' . e($job->job_no) . '</li>'
            . '</ol>';

        // Schema items use clean slug URLs (better for SEO)
        $items = [
            ['name' => 'Home', 'url' => $homeUrl],
            ['name' => $prefectureName, 'url' => url('part-time-jobs-in-' . strtolower($prefectureName))],
            ['name' => $areaName, 'url' => url('part-time-jobs-in-' . strtolower(str_replace(' ', '-', $areaName)))],
            ['name' => $job->title],
        ];

        return ['html' => $html, 'items' => $items];
    }
}
