<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\MovedTargetMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Link-check upload: extend the jobs the Mac-side scan found still live.
 *
 * The scan (scripts/link-check.py) runs on a Mac behind a Japan VPN, because
 * shigotoin returns 403 to the SiteGround IP. It produces a results CSV. This
 * page is how that CSV gets back into the database.
 *
 * WHY A CSV AND NOT AN ENDPOINT THE SCRIPT POSTS TO
 * AdminMiddleware is session-only; there is no routes/api.php and no token
 * auth. A machine-callable endpoint would mean inventing a token scheme,
 * storing a secret on the Mac and adding a CSRF exception -- new public write
 * surface. An upload costs one click and adds none.
 *
 * WHAT IT WILL AND WILL NOT DO
 *   OPEN         -> delete_at += 7
 *   MOVED        -> only if MovedTargetMatcher confirms the destination really
 *                   is our job: apply_link rewritten, old value kept in
 *                   job_apply_link_history, delete_at += 7
 *   WRONG_TARGET -> a MOVED row whose destination is a different employer or
 *                   place, or whose destination title was never captured.
 *                   Listed for staff. NEVER relinked.
 *   CLOSED/GONE/ERROR -> listed for staff, never written, never auto-expired.
 *                   An ERROR means the check failed, not that the job did.
 *
 * Jobs sharing one final offer are grouped and held back from the automatic
 * path. Most are true duplicates, but not all: offer/118120821 was claimed by
 * two unrelated employers in different prefectures, so "keep one, trash the
 * other" would have destroyed a valid listing. The group verdict decides which
 * actions the page is even willing to offer.
 *
 * Nothing is applied on upload. Upload previews; a second, explicit
 * confirmation writes.
 */
class LinkCheckController extends Controller
{
    /** Classifications this page is willing to act on. */
    private const ACTIONABLE = ['OPEN', 'MOVED'];

    /** Days added to delete_at for a job confirmed still live. */
    private const EXTEND_DAYS = 7;

    private const STAGE_DIR = 'link-check';

    /** Staged uploads older than this are swept up. */
    private const STAGE_TTL_HOURS = 24;

    /** Group cards rendered on a plain page load before "show all". */
    private const GROUP_PAGE_CAP = 50;

    /** Same gate as the other analytics pages, reading the one shared constant. */
    private function authorizeSuperAdmin(): void
    {
        $user = session('user');
        if (! $user || ($user->email ?? null) !== AnalyticsController::SUPER_ADMIN_EMAIL) {
            abort(403, 'You are not authorized.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->sweepStaleUploads();

        $open = $this->loadOpenItems($request->boolean('all'));

        return view('admin.analytics.link-check', [
            'activeSideMenu' => 'link_check',
            'preview'        => null,
            'open'           => $open,
            'recent'         => $this->recentBatches(),
        ]);
    }

    /**
     * Stage 1: parse the CSV, resolve it against the live rows, show a diff.
     * Writes nothing to `jobs`.
     */
    public function preview(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:10240']);

        $upload = $request->file('csv_file');
        $path = $upload->getRealPath();
        [$rows, $parseError] = $this->parseResultsCsv($path);

        if ($parseError !== null) {
            return back()->with('error', $parseError);
        }
        if (! $rows) {
            return back()->with('error', 'No usable rows found in that CSV.');
        }

        // Keep the upload so the apply step re-reads the file rather than
        // trusting anything round-tripped through the browser.
        $token = Str::uuid()->toString();
        $dir = storage_path('app/' . self::STAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        copy($path, $dir . '/' . $token . '.csv');
        file_put_contents($dir . '/' . $token . '.name', $upload->getClientOriginalName());

        return $this->renderPreview($token, $this->buildPlan($rows));
    }

    /**
     * Stage 2: apply. Re-reads the staged file and re-derives the plan, so a
     * row that changed between preview and apply is re-evaluated, not replayed.
     */
    public function apply(Request $request)
    {
        $this->authorizeSuperAdmin();

        [$token, $file] = $this->stagedFile($request);
        if (! $file) {
            return redirect()->route('admin.analytics.link-check')
                ->with('error', 'That upload has expired — please upload the CSV again.');
        }

        [$rows, $parseError] = $this->parseResultsCsv($file);
        if ($parseError !== null) {
            return back()->with('error', $parseError);
        }

        $plan = $this->buildPlan($rows);
        $batch = 'lc-' . date('Ymd-His') . '-' . substr($token, 0, 8);
        $actor = $this->actorName();

        $extended = 0;
        $relinked = 0;
        $skipped = 0;

        DB::transaction(function () use ($plan, $batch, $actor, &$extended, &$relinked, &$skipped) {
            foreach ($plan['apply'] as $row) {
                // Guarded write: status must still be Published AND the stored
                // apply_link must still be the URL the scan actually fetched.
                $affected = DB::table('jobs')
                    ->where('job_no', $row['job_no'])
                    ->where('job_status_id', Job::STATUS_PUBLISHED)
                    ->where('apply_link', $row['current_link'])
                    ->update(array_filter([
                        'apply_link' => $row['new_link'] !== $row['current_link'] ? $row['new_link'] : null,
                        'delete_at'  => $row['new_delete_at'],
                    ], fn ($v) => $v !== null));

                if ($affected === 0) {
                    $skipped++;
                    continue;
                }

                $extended++;
                $this->logStatusChange($row['job_no'], Job::STATUS_PUBLISHED, Job::STATUS_PUBLISHED,
                    $row['old_delete_at'], $row['new_delete_at'], 'link-check', $actor, $batch,
                    $row['class'] === 'MOVED'
                        ? 'still live at a new offer id; link rewritten, delete_at +' . self::EXTEND_DAYS
                        : 'confirmed still live; delete_at +' . self::EXTEND_DAYS,
                    $row['checked_at']);

                if ($row['new_link'] !== $row['current_link']) {
                    $relinked++;
                    DB::table('job_apply_link_history')->insert([
                        'job_no'         => $row['job_no'],
                        'old_apply_link' => $row['current_link'],
                        'new_apply_link' => $row['new_link'],
                        'http_code'      => $row['http_code'],
                        'num_redirects'  => $row['num_redirects'],
                        'checked_at'     => $row['checked_at'],
                        'source'         => 'link-check',
                        'actor'          => $actor,
                        'batch'          => $batch,
                        'created_at'     => now(),
                    ]);
                }
            }
        });

        $kept = $this->persistOpenItems($plan, $batch, $actor);

        $this->logBatch($batch, $actor, $plan, $extended, $relinked, $skipped);
        $this->discardStaged($token);

        $msg = "{$extended} job(s) extended by " . self::EXTEND_DAYS . ' days'
             . ($relinked ? ", {$relinked} apply link(s) rewritten" : '')
             . ($skipped ? ", {$skipped} skipped (changed since the scan)" : '')
             . ". Batch {$batch}."
             . ($kept ? "  {$kept} open item(s) saved for review below." : '');

        return redirect()->route('admin.analytics.link-check')->with('success', $msg);
    }

    /** Throw the staged upload away without writing anything. */
    public function cancel(Request $request)
    {
        $this->authorizeSuperAdmin();
        [$token, $file] = $this->stagedFile($request);
        if ($file) {
            $this->discardStaged($token);
        }

        return redirect()->route('admin.analytics.link-check')
            ->with('success', 'Upload discarded. Nothing was changed.');
    }

    /**
     * Act on one same-final-offer group: keep the oldest, keep the newest, or
     * remember that it is not a duplicate at all.
     */
    public function groupAction(Request $request)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'action'    => 'required|in:keep_older,keep_newer,not_duplicate',
            'final_url' => 'required|string|max:2048',
            'job_nos'   => 'required|string|max:2048',
            'token'     => 'nullable|string|max:64',
        ]);

        $jobNos = array_values(array_filter(array_map('trim', explode(',', $data['job_nos']))));
        $actor = $this->actorName();
        $user = session('user');

        if ($data['action'] === 'not_duplicate') {
            DB::table('dismissed_duplicates')->insertOrIgnore([
                'group_hash'   => MovedTargetMatcher::groupHash($data['final_url']),
                'dismissed_by' => $user->id ?? 0,
                'dismissed_at' => now(),
            ]);

            DB::table('link_check_open_items')
                ->where('group_hash', MovedTargetMatcher::groupHash($data['final_url']))
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $actor,
                          'resolved_action' => 'not_duplicate', 'updated_at' => now()]);

            return back()->with('success',
                'Marked as not a duplicate — this group will not be shown again.');
        }

        $jobs = DB::table('jobs')->whereIn('job_no', $jobNos)
            ->where('job_status_id', Job::STATUS_PUBLISHED)
            ->select('job_no', 'date', 'delete_at')->get();

        if ($jobs->count() < 2) {
            return back()->with('error',
                'Those jobs changed since the preview — reload and check them again. Nothing was changed.');
        }

        $sorted = $jobs->sortBy('date')->values();
        $keep = $data['action'] === 'keep_older' ? $sorted->first() : $sorted->last();
        $trash = $sorted->reject(fn ($j) => $j->job_no === $keep->job_no);

        $batch = 'lca-' . date('Ymd-His');
        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($trash, $batch, $actor, $keep, &$done, &$skipped) {
            foreach ($trash as $j) {
                // updated_at IS bumped: the expired-page tier is computed from
                // Expire_Date ?? updated_at, so a stale value would send a
                // just-trashed page straight to a 301 or 410.
                $n = DB::table('jobs')->where('job_no', $j->job_no)
                    ->where('job_status_id', Job::STATUS_PUBLISHED)
                    ->update(['job_status_id' => Job::STATUS_TRASHED, 'updated_at' => now()]);

                if ($n === 0) {
                    $skipped++;
                    continue;
                }
                $done++;
                $this->logStatusChange($j->job_no, Job::STATUS_PUBLISHED, Job::STATUS_TRASHED,
                    $j->delete_at, $j->delete_at, 'link-check-action', $actor, $batch,
                    "duplicate of job {$keep->job_no} (same final offer)");
            }
        });

        // the group is settled either way
        $this->resolveForJobs($jobNos, 'kept_' . substr($data['action'], 5), $actor);

        $msg = "Kept job {$keep->job_no}; trashed {$done} duplicate(s)."
             . ($skipped ? " {$skipped} skipped (changed since the preview)." : '');

        return back()->with('success', $msg);
    }

    /** Act on one staff-review row: expire it, or give it another 7 days. */
    public function rowAction(Request $request)
    {
        $this->authorizeSuperAdmin();

        $data = $request->validate([
            'action'       => 'required|in:expire,extend',
            'job_no'       => 'required|string|max:20',
            'current_link' => 'required|string|max:2048',
        ]);

        $job = DB::table('jobs')->where('job_no', $data['job_no'])
            ->select('job_no', 'job_status_id', 'delete_at', 'apply_link')->first();

        if (! $job
            || (int) $job->job_status_id !== Job::STATUS_PUBLISHED
            || trim((string) $job->apply_link) !== trim($data['current_link'])) {
            return back()->with('error',
                "Job {$data['job_no']} changed since the preview — nothing was changed. Reload and check it again.");
        }

        $batch = 'lca-' . date('Ymd-His');
        $actor = $this->actorName();

        if ($data['action'] === 'expire') {
            $n = DB::table('jobs')->where('job_no', $job->job_no)
                ->where('job_status_id', Job::STATUS_PUBLISHED)
                ->where('apply_link', $job->apply_link)
                ->update(['job_status_id' => Job::STATUS_EXPIRED, 'updated_at' => now()]);

            if ($n === 0) {
                return back()->with('error', "Job {$job->job_no} changed — nothing was changed.");
            }
            $this->logStatusChange($job->job_no, Job::STATUS_PUBLISHED, Job::STATUS_EXPIRED,
                $job->delete_at, $job->delete_at, 'link-check-action', $actor, $batch,
                'expired from link-check review');

            $this->resolveForJobs([$job->job_no], 'expired', $actor);

            return back()->with('success', "Job {$job->job_no} expired.");
        }

        $base = $job->delete_at ? strtotime($job->delete_at) : time();
        $new = date('Y-m-d H:i:s', $base + self::EXTEND_DAYS * 86400);

        $n = DB::table('jobs')->where('job_no', $job->job_no)
            ->where('job_status_id', Job::STATUS_PUBLISHED)
            ->where('apply_link', $job->apply_link)
            ->update(['delete_at' => $new]);

        if ($n === 0) {
            return back()->with('error', "Job {$job->job_no} changed — nothing was changed.");
        }
        $this->logStatusChange($job->job_no, Job::STATUS_PUBLISHED, Job::STATUS_PUBLISHED,
            $job->delete_at, $new, 'link-check-action', $actor, $batch,
            'extended +' . self::EXTEND_DAYS . ' days from link-check review');

        $this->resolveForJobs([$job->job_no], 'extended', $actor);

        return back()->with('success',
            "Job {$job->job_no} extended to " . date('d M Y', strtotime($new)) . '.');
    }

    // ──────────────────────────────────────────────────────────────────────

    private function renderPreview(string $token, array $plan)
    {
        $dir = storage_path('app/' . self::STAGE_DIR);
        $nameFile = $dir . '/' . $token . '.name';
        $csv = $dir . '/' . $token . '.csv';

        return view('admin.analytics.link-check', [
            'activeSideMenu' => 'link_check',
            'open'           => null,
            'preview'        => $plan + [
                'token'       => $token,
                'file_name'   => is_file($nameFile) ? file_get_contents($nameFile) : 'uploaded.csv',
                // raw unix mtime; the view formats it in the reader's timezone
                'uploaded_at' => is_file($csv) ? filemtime($csv) : null,
            ],
            'recent'         => $this->recentBatches(),
        ]);
    }

    /** @return array{0:string,1:?string} [token, path or null] */
    private function stagedFile(Request $request): array
    {
        $token = preg_replace('/[^a-z0-9\-]/i', '', (string) $request->input('token', ''));
        if ($token === '') {
            return ['', null];
        }
        $file = storage_path('app/' . self::STAGE_DIR . '/' . $token . '.csv');

        return [$token, is_file($file) ? $file : null];
    }

    private function discardStaged(string $token): void
    {
        $dir = storage_path('app/' . self::STAGE_DIR);
        @unlink($dir . '/' . $token . '.csv');
        @unlink($dir . '/' . $token . '.name');
    }

    /** Only the apply path used to clean up; an abandoned preview lingered. */
    private function sweepStaleUploads(): void
    {
        $dir = storage_path('app/' . self::STAGE_DIR);
        if (! is_dir($dir)) {
            return;
        }
        $cutoff = time() - self::STAGE_TTL_HOURS * 3600;
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < $cutoff) {
                @unlink($f);
            }
        }
    }

    /**
     * @param  ?string  $sourceCheckedAt  when the scan result behind this change
     *   was observed. Stored so a re-upload of the same results file becomes a
     *   no-op; null for manual actions, which have no scan behind them.
     */
    private function logStatusChange(string $jobNo, ?int $old, ?int $new, $oldDeleteAt, $newDeleteAt,
                                     string $source, string $actor, string $batch, string $reason,
                                     ?string $sourceCheckedAt = null): void
    {
        DB::table('job_status_history')->insert([
            'job_no'            => $jobNo,
            'old_status'        => $old,
            'new_status'        => $new,
            'old_delete_at'     => $oldDeleteAt,
            'new_delete_at'     => $newDeleteAt,
            'source'            => $source,
            'actor'             => $actor,
            'reason'            => $reason,
            'batch'             => $batch,
            'source_checked_at' => $sourceCheckedAt,
            'created_at'        => now(),
        ]);
    }

    /**
     * Newest scan timestamp already applied per job, so the planner can skip a
     * result it has seen before. Wrapped: the column arrives in a migration and
     * a missing column must not take the page down.
     *
     * @return array<string,string> job_no => 'Y-m-d H:i:s'
     */
    private function lastAppliedCheckedAt(array $jobNos): array
    {
        if (! $jobNos) {
            return [];
        }
        try {
            return DB::table('job_status_history')
                ->whereIn('job_no', $jobNos)
                ->where('source', 'link-check')
                ->whereNotNull('source_checked_at')
                ->groupBy('job_no')
                ->pluck(DB::raw('MAX(source_checked_at)'), 'job_no')
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('link-check: could not read source_checked_at', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Read the scan's results CSV. Returns [rows, errorMessage|null].
     * Tolerates the append-only file: the LAST row for a job_no wins.
     */
    private function parseResultsCsv(string $path): array
    {
        $fh = @fopen($path, 'r');
        if (! $fh) {
            return [[], 'Could not read the uploaded file.'];
        }

        $header = fgetcsv($fh, 0, ',', '"', '');
        if (! $header) {
            fclose($fh);
            return [[], 'That file is empty.'];
        }
        $header = array_map(fn ($h) => trim((string) $h), $header);

        $required = ['job_no', 'requested_url', 'final_url', 'classification'];
        $missing = array_diff($required, $header);
        if ($missing) {
            fclose($fh);
            return [[], 'That does not look like a link-check results file — missing column(s): '
                . implode(', ', $missing) . '.'];
        }

        $latest = [];
        while (($line = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if (count($line) === 1 && ($line[0] === null || trim((string) $line[0]) === '')) {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
            $jobNo = trim((string) ($row['job_no'] ?? ''));
            $class = trim((string) ($row['classification'] ?? ''));
            if ($jobNo === '' || $class === '') {
                continue;   // blank or half-written line from an interrupted run
            }
            $latest[$jobNo] = $row;
        }
        fclose($fh);

        return [array_values($latest), null];
    }

    /**
     * Turn parsed rows into an apply list plus the things a human must look at.
     */
    private function buildPlan(array $rows): array
    {
        $matcher = new MovedTargetMatcher();

        $byJobNo = [];
        foreach ($rows as $r) {
            $byJobNo[trim((string) $r['job_no'])] = $r;
        }

        $jobs = DB::table('jobs')
            ->whereIn('job_no', array_keys($byJobNo))
            ->select('job_no', 'title', 'job_status_id', 'apply_link', 'delete_at',
                     'company_name', 'address', 'date')
            ->get()
            ->keyBy('job_no');

        $dismissed = DB::table('dismissed_duplicates')->pluck('group_hash')->flip();
        $lastApplied = $this->lastAppliedCheckedAt(array_keys($byJobNo));

        // A MOVED row is only actionable if the destination really is our job.
        $wrongTarget = [];
        foreach ($byJobNo as $jobNo => $r) {
            if (($r['classification'] ?? '') !== 'MOVED') {
                continue;
            }
            $job = $jobs[$jobNo] ?? null;
            if (! $job) {
                continue;
            }
            $verdict = $matcher->matches($job->company_name, $job->address, $r['source_title'] ?? '');
            if (! $verdict['same']) {
                $byJobNo[$jobNo]['classification'] = 'WRONG_TARGET';
                $byJobNo[$jobNo]['note'] = $verdict['reason'];
                $wrongTarget[$jobNo] = true;
            }
        }

        // Jobs landing on one final offer: group them, unless staff already
        // said the group is not a duplicate.
        $finalMembers = [];
        foreach ($byJobNo as $jobNo => $r) {
            if (! in_array($r['classification'], self::ACTIONABLE, true)) {
                continue;
            }
            // Only live jobs can be duplicates of each other. A retired job
            // counted here would appear in its group card AND in the skipped
            // count, so the preview totals exceeded the row count -- and the
            // card would offer a keep/trash the guards then refuse anyway.
            $job = $jobs[$jobNo] ?? null;
            if (! $job || (int) $job->job_status_id !== Job::STATUS_PUBLISHED) {
                continue;
            }
            $final = trim((string) $r['final_url']);
            if ($final !== '') {
                $finalMembers[$final][] = $jobNo;
            }
        }
        $heldByGroup = [];
        $groups = [];
        foreach ($finalMembers as $final => $members) {
            if (count($members) < 2) {
                continue;
            }
            $hash = MovedTargetMatcher::groupHash($final);
            if (isset($dismissed[$hash])) {
                continue;   // staff said these are different jobs; let them through
            }
            $groups[] = $this->buildGroup($final, $hash, $members, $jobs, $byJobNo, $matcher);
            foreach ($members as $m) {
                $heldByGroup[$m] = true;
            }
        }
        // Different-jobs groups first, then the largest, then by offer.
        usort($groups, fn ($a, $b) => [$a['verdict'] === 'same', -count($a['jobs'])]
                                   <=> [$b['verdict'] === 'same', -count($b['jobs'])]);

        $apply = $review = $notFound = $notPublished = $alreadyApplied = [];

        foreach ($byJobNo as $jobNo => $r) {
            $class = $r['classification'];
            $job = $jobs[$jobNo] ?? null;

            if (! $job) {
                $notFound[] = ['job_no' => $jobNo, 'class' => $class];
                continue;
            }

            if (! in_array($class, self::ACTIONABLE, true)) {
                $review[] = $this->reviewRow($jobNo, $class, $job, $r, $r['note'] ?? '');
                continue;
            }

            if ((int) $job->job_status_id !== Job::STATUS_PUBLISHED) {
                $notPublished[] = ['job_no' => $jobNo, 'class' => $class,
                                   'status' => (int) $job->job_status_id, 'title' => $job->title];
                continue;
            }

            if (isset($heldByGroup[$jobNo])) {
                continue;   // shown in its group card instead
            }

            // Stale row: the stored link is no longer what the scan fetched.
            if (trim((string) $job->apply_link) !== trim((string) $r['requested_url'])) {
                $review[] = $this->reviewRow($jobNo, $class, $job, $r,
                    'apply_link changed since the scan — re-scan this job');
                continue;
            }

            $checkedAt = ! empty($r['checked_at']) ? date('Y-m-d H:i:s', strtotime($r['checked_at'])) : null;

            // Already acted on this scan result (or a newer one). Re-uploading
            // the same file must not stack another +7 days: MOVED rows are
            // protected by the apply_link guard, OPEN rows are not.
            if ($checkedAt !== null
                && isset($lastApplied[$jobNo])
                && strtotime((string) $lastApplied[$jobNo]) >= strtotime($checkedAt)) {
                $alreadyApplied[] = [
                    'job_no'      => $jobNo,
                    'class'       => $class,
                    'title'       => $job->title,
                    'applied_at'  => $lastApplied[$jobNo],
                    'checked_at'  => $checkedAt,
                ];
                continue;
            }

            $final = trim((string) $r['final_url']);
            $old = $job->delete_at;
            $base = $old ? strtotime($old) : time();

            $apply[] = [
                'job_no'        => $jobNo,
                'class'         => $class,
                'title'         => $job->title,
                'current_link'  => trim((string) $job->apply_link),
                'new_link'      => $final !== '' ? $final : trim((string) $job->apply_link),
                'old_delete_at' => $old,
                'new_delete_at' => date('Y-m-d H:i:s', $base + self::EXTEND_DAYS * 86400),
                'http_code'     => $r['http_code'] ?? null,
                'num_redirects' => is_numeric($r['num_redirects'] ?? null) ? (int) $r['num_redirects'] : null,
                'checked_at'    => $checkedAt,
            ];
        }

        usort($apply, fn ($a, $b) => [$a['class'], $a['job_no']] <=> [$b['class'], $b['job_no']]);

        $groupJobCount = array_sum(array_map(fn ($g) => count($g['jobs']), $groups));

        return [
            'apply'          => $apply,
            'review'         => $review,
            'groups'         => $groups,
            'notFound'       => $notFound,
            'notPublished'   => $notPublished,
            'alreadyApplied' => $alreadyApplied,
            'counts'       => [
                'rows'         => count($byJobNo),
                'apply'        => count($apply),
                'moved'        => count(array_filter($apply, fn ($x) => $x['class'] === 'MOVED')),
                'open'         => count(array_filter($apply, fn ($x) => $x['class'] === 'OPEN')),
                'review'       => count($review),
                'wrongTarget'  => count($wrongTarget),
                'groups'       => count($groups),
                'groupJobs'    => $groupJobCount,
                'groupsDiff'   => count(array_filter($groups, fn ($g) => $g['verdict'] !== 'same')),
                'notFound'     => count($notFound),
                'notPublished' => count($notPublished),
                'alreadyApplied' => count($alreadyApplied),
            ],
        ];
    }

    private function reviewRow(string $jobNo, string $class, $job, array $r, string $note): array
    {
        return [
            'job_no'       => $jobNo,
            'class'        => $class,
            'title'        => $job->title,
            'company'      => $job->company_name,
            'url'          => $r['requested_url'] ?? '',
            'final_url'    => $r['final_url'] ?? '',
            'page_title'   => $r['source_title'] ?? '',
            'current_link' => trim((string) $job->apply_link),
            'status'       => (int) $job->job_status_id,
            'delete_at'    => $job->delete_at,
            'note'         => $note !== '' ? $note : (string) ($r['note'] ?? ''),
        ];
    }

    /** One same-final-offer card: the jobs, the verdict, and the evidence. */
    private function buildGroup(string $final, string $hash, array $members, $jobs,
                                array $byJobNo, MovedTargetMatcher $matcher): array
    {
        $rows = [];
        foreach ($members as $jobNo) {
            $j = $jobs[$jobNo];
            $rows[] = [
                'job_no'    => $jobNo,
                'title'     => $j->title,
                'company'   => $j->company_name,
                'address'   => $j->address,
                'created'   => $j->date,
                'delete_at' => $j->delete_at,
                'status'    => (int) $j->job_status_id,
                'link'      => $j->apply_link,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['created'], (string) $b['created']));
        $rows[0]['is_oldest'] = true;

        $pageTitle = '';
        foreach ($members as $m) {
            if (! empty($byJobNo[$m]['source_title'])) {
                $pageTitle = $byJobNo[$m]['source_title'];
                break;
            }
        }

        $verdict = $matcher->sameGroup(array_map(
            fn ($r) => ['company_name' => $r['company'], 'address' => $r['address']], $rows));

        // Which of OUR jobs does the destination page actually describe? On a
        // "different jobs" group this is the one that legitimately owns the
        // link — the others have the wrong link stored and need fixing by hand.
        foreach ($rows as $i => $r) {
            $m = $matcher->matches($r['company'], $r['address'], $pageTitle);
            $rows[$i]['matches_page'] = $m['same'];
            $rows[$i]['match_reason'] = $m['reason'];
            $rows[$i]['is_oldest'] = $r['is_oldest'] ?? false;
        }
        $owner = null;
        foreach ($rows as $r) {
            if ($r['matches_page']) {
                $owner = $r['job_no'];
                break;
            }
        }

        return [
            'final'        => $final,
            'hash'         => $hash,
            'page_title'   => $pageTitle,
            'jobs'         => $rows,
            'verdict'      => $verdict['same'] ? 'same' : 'different',
            'verdict_text' => $verdict['same']
                ? 'Same job — duplicate'
                : 'Different jobs — shigotoin redirect',
            'evidence'     => $verdict['reason']
                . ($verdict['shared_address'] !== ''
                    ? ' (shared address text “' . $verdict['shared_address'] . '”)' : ''),
            'owner'        => $owner,
            'job_nos'      => implode(',', array_column($rows, 'job_no')),
        ];
    }


    // ── open items ────────────────────────────────────────────────────────

    /**
     * Save the work this Apply surfaced but did not do.
     *
     * Deliberately NOT saved:
     *   - "apply_link changed since the scan" rows. We rewrote those links
     *     ourselves, so the CSV no longer matches — the job is already handled.
     *     On 2026-10-23 there were 458 of them against 4 real items; keeping
     *     them would bury the real work.
     *   - ERROR rows. An ERROR means the check failed, not that the job did,
     *     and the next scan retries them first.
     *   - anything no longer Published. Nothing to act on.
     *
     * Upsert on item_key with resolved_at cleared, so an item that reappears
     * in a later scan reopens instead of duplicating.
     *
     * @return int rows written or reopened
     */
    private function persistOpenItems(array $plan, string $batch, string $actor): int
    {
        $now = now();
        $n = 0;

        foreach ($plan['review'] as $row) {
            if ((int) $row['status'] !== Job::STATUS_PUBLISHED) {
                continue;
            }
            if ($row['class'] === 'ERROR') {
                continue;
            }
            if (str_contains((string) $row['note'], 'changed since the scan')) {
                continue;
            }
            $this->upsertOpenItem('review:' . $row['job_no'], [
                'kind'           => 'review',
                'job_no'         => $row['job_no'],
                'group_hash'     => null,
                'final_url'      => $row['final_url'] ?? null,
                'page_title'     => mb_substr((string) ($row['page_title'] ?? ''), 0, 255),
                'requested_url'  => $row['url'] ?? null,
                'classification' => $row['class'],
                'note'           => mb_substr((string) $row['note'], 0, 255),
            ], $batch, $now);
            $n++;
        }

        foreach ($plan['groups'] as $g) {
            foreach ($g['jobs'] as $j) {
                if ((int) $j['status'] !== Job::STATUS_PUBLISHED) {
                    continue;
                }
                $this->upsertOpenItem('group:' . $g['hash'] . ':' . $j['job_no'], [
                    'kind'           => 'group',
                    'job_no'         => $j['job_no'],
                    'group_hash'     => $g['hash'],
                    'final_url'      => $g['final'],
                    'page_title'     => mb_substr((string) $g['page_title'], 0, 255),
                    'requested_url'  => $j['link'] ?? null,
                    'classification' => 'SAME_FINAL_OFFER',
                    'note'           => mb_substr((string) $g['evidence'], 0, 255),
                ], $batch, $now);
                $n++;
            }
        }

        return $n;
    }

    private function upsertOpenItem(string $key, array $data, string $batch, $now): void
    {
        $existing = DB::table('link_check_open_items')->where('item_key', $key)->first();

        if ($existing) {
            DB::table('link_check_open_items')->where('item_key', $key)->update($data + [
                'last_seen_batch' => $batch,
                'resolved_at'     => null,     // seen again -> reopen
                'resolved_by'     => null,
                'resolved_action' => null,
                'updated_at'      => $now,
            ]);
            return;
        }

        DB::table('link_check_open_items')->insert($data + [
            'item_key'         => $key,
            'first_seen_batch' => $batch,
            'last_seen_batch'  => $batch,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    /**
     * Unresolved items, joined to live job state, with anything that no longer
     * applies resolved on the way through. Self-healing: a job retired or
     * relinked elsewhere drops off without anyone clicking.
     *
     * Shapes match buildPlan() exactly so the preview and this list share the
     * same Blade partials and cannot drift apart.
     */
    private function loadOpenItems(bool $showAll = false): array
    {
        try {
            $items = DB::table('link_check_open_items')->whereNull('resolved_at')->get();
        } catch (\Throwable $e) {
            return ['groups' => [], 'review' => [], 'counts' => [
                'groups' => 0, 'groupJobs' => 0, 'review' => 0, 'groupsDiff' => 0,
                'shown' => 0, 'capped' => false,
            ]];
        }
        if ($items->isEmpty()) {
            return ['groups' => [], 'review' => [], 'counts' => [
                'groups' => 0, 'groupJobs' => 0, 'review' => 0, 'groupsDiff' => 0,
                'shown' => 0, 'capped' => false,
            ]];
        }

        $jobs = DB::table('jobs')->whereIn('job_no', $items->pluck('job_no')->unique()->all())
            ->select('job_no', 'title', 'job_status_id', 'apply_link', 'delete_at',
                     'company_name', 'address', 'date')
            ->get()->keyBy('job_no');
        $dismissed = DB::table('dismissed_duplicates')->pluck('group_hash')->flip();

        $resolve = [];      // item_key => action
        $review = [];
        $byGroup = [];

        foreach ($items as $it) {
            $job = $jobs[$it->job_no] ?? null;

            if (! $job) {
                $resolve[$it->item_key] = 'job_missing';
                continue;
            }
            if ((int) $job->job_status_id !== Job::STATUS_PUBLISHED) {
                $resolve[$it->item_key] = 'retired_elsewhere';
                continue;
            }
            if ($it->requested_url && trim((string) $job->apply_link) !== trim((string) $it->requested_url)) {
                $resolve[$it->item_key] = 'fixed_by_hand';
                continue;
            }
            if ($it->kind === 'group' && isset($dismissed[$it->group_hash])) {
                $resolve[$it->item_key] = 'dismissed';
                continue;
            }

            if ($it->kind === 'review') {
                $review[] = [
                    'item_key' => $it->item_key,
                    'job_no'   => $it->job_no,
                    'class'    => $it->classification,
                    'title'    => $job->title,
                    'company'  => $job->company_name,
                    'url'      => $it->requested_url,
                    'final_url' => $it->final_url,
                    'page_title' => $it->page_title,
                    'current_link' => trim((string) $job->apply_link),
                    'status'   => (int) $job->job_status_id,
                    'delete_at' => $job->delete_at,
                    'note'     => $it->note,
                ];
            } else {
                $byGroup[$it->group_hash][] = [$it, $job];
            }
        }

        // A group needs at least two live members to still be a duplicate.
        $matcher = new MovedTargetMatcher();
        $groups = [];
        foreach ($byGroup as $hash => $members) {
            if (count($members) < 2) {
                foreach ($members as [$it, $_]) {
                    $resolve[$it->item_key] = 'no_longer_duplicate';
                }
                continue;
            }
            $rows = [];
            foreach ($members as [$it, $job]) {
                $rows[] = [
                    'item_key' => $it->item_key,
                    'job_no' => $it->job_no, 'title' => $job->title,
                    'company' => $job->company_name, 'address' => $job->address,
                    'created' => $job->date, 'delete_at' => $job->delete_at,
                    'status' => (int) $job->job_status_id, 'link' => $job->apply_link,
                ];
            }
            usort($rows, fn ($a, $b) => strcmp((string) $a['created'], (string) $b['created']));
            $rows[0]['is_oldest'] = true;

            $first = $members[0][0];
            $pageTitle = (string) $first->page_title;
            $verdict = $matcher->sameGroup(array_map(
                fn ($r) => ['company_name' => $r['company'], 'address' => $r['address']], $rows));

            foreach ($rows as $i => $r) {
                $m = $matcher->matches($r['company'], $r['address'], $pageTitle);
                $rows[$i]['matches_page'] = $m['same'];
                $rows[$i]['match_reason'] = $m['reason'];
                $rows[$i]['is_oldest'] = $r['is_oldest'] ?? false;
            }
            $owner = null;
            foreach ($rows as $r) {
                if ($r['matches_page']) { $owner = $r['job_no']; break; }
            }

            $groups[] = [
                'final' => (string) $first->final_url, 'hash' => $hash,
                'page_title' => $pageTitle, 'jobs' => $rows,
                'verdict' => $verdict['same'] ? 'same' : 'different',
                'verdict_text' => $verdict['same'] ? 'Same job — duplicate' : 'Different jobs — shigotoin redirect',
                'evidence' => $verdict['reason'] . ($verdict['shared_address'] !== ''
                    ? ' (shared address text “' . $verdict['shared_address'] . '”)' : ''),
                'owner' => $owner,
                'job_nos' => implode(',', array_column($rows, 'job_no')),
            ];
        }

        if ($resolve) {
            $this->markResolved(array_keys($resolve), $resolve, 'auto');
        }

        usort($groups, fn ($a, $b) => [$a['verdict'] === 'same', -count($a['jobs'])]
                                   <=> [$b['verdict'] === 'same', -count($b['jobs'])]);
        $total = count($groups);
        $capped = ! $showAll && $total > self::GROUP_PAGE_CAP;
        $shown = $capped ? array_slice($groups, 0, self::GROUP_PAGE_CAP) : $groups;

        return [
            'groups' => $shown,
            'review' => $review,
            'counts' => [
                'groups'     => $total,
                'groupJobs'  => array_sum(array_map(fn ($g) => count($g['jobs']), $groups)),
                'groupsDiff' => count(array_filter($groups, fn ($g) => $g['verdict'] !== 'same')),
                'review'     => count($review),
                'shown'      => count($shown),
                'capped'     => $capped,
            ],
        ];
    }

    /** @param array<int,string> $keys */
    private function markResolved(array $keys, array $actions, string $by): void
    {
        if (! $keys) {
            return;
        }
        try {
            foreach (array_chunk($keys, 200) as $chunk) {
                foreach ($chunk as $k) {
                    DB::table('link_check_open_items')->where('item_key', $k)
                        ->whereNull('resolved_at')
                        ->update([
                            'resolved_at'     => now(),
                            'resolved_by'     => $by === 'auto' ? 'auto' : $by,
                            'resolved_action' => $actions[$k] ?? 'resolved',
                            'updated_at'      => now(),
                        ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('link-check: could not resolve open items', ['error' => $e->getMessage()]);
        }
    }

    /** Resolve every open item for these job numbers (used by the action handlers). */
    private function resolveForJobs(array $jobNos, string $action, string $by): void
    {
        if (! $jobNos) {
            return;
        }
        try {
            DB::table('link_check_open_items')
                ->whereIn('job_no', $jobNos)->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $by,
                          'resolved_action' => $action, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('link-check: resolveForJobs failed', ['error' => $e->getMessage()]);
        }
    }

    /** The "Done" button: clear an item without changing the job. */
    public function doneItem(Request $request)
    {
        $this->authorizeSuperAdmin();
        $data = $request->validate([
            'item_key' => 'nullable|string|max:96',
            'group_hash' => 'nullable|string|max:64',
        ]);
        $actor = $this->actorName();

        if (! empty($data['group_hash'])) {
            DB::table('link_check_open_items')
                ->where('group_hash', $data['group_hash'])->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_by' => $actor,
                          'resolved_action' => 'done_by_hand', 'updated_at' => now()]);
            return back()->with('success', 'Group marked done. It will not be shown again.');
        }
        if (! empty($data['item_key'])) {
            $this->markResolved([$data['item_key']], [$data['item_key'] => 'done_by_hand'], $actor);
            return back()->with('success', 'Item marked done.');
        }

        return back()->with('error', 'Nothing to mark done.');
    }

    /** What one batch actually changed. */
    public function batchDetail(Request $request, string $batch)
    {
        $this->authorizeSuperAdmin();

        if (! preg_match('/^[a-z]{2,3}-\d{8}-\d{6}(-[a-z0-9]{8})?$/i', $batch)) {
            return redirect()->route('admin.analytics.link-check')
                ->with('error', 'That batch id is not valid.');
        }

        $status = DB::table('job_status_history as h')
            ->leftJoin('jobs as j', 'j.job_no', '=', 'h.job_no')
            ->where('h.batch', $batch)
            ->select('h.job_no', 'h.old_status', 'h.new_status', 'h.old_delete_at',
                     'h.new_delete_at', 'h.reason', 'h.actor', 'h.source',
                     'h.source_checked_at', 'h.created_at', 'j.title', 'j.job_status_id')
            ->orderBy('h.id')->get();

        if ($status->isEmpty()) {
            return redirect()->route('admin.analytics.link-check')
                ->with('error', "No history found for batch {$batch}.");
        }

        $links = DB::table('job_apply_link_history')->where('batch', $batch)
            ->select('job_no', 'old_apply_link', 'new_apply_link', 'http_code', 'num_redirects')
            ->orderBy('id')->get();

        return view('admin.analytics.link-check-batch', [
            'activeSideMenu' => 'link_check',
            'batch'   => $batch,
            'status'  => $status,
            'links'   => $links,
            'summary' => [
                'rows'      => $status->count(),
                'relinks'   => $links->count(),
                'actor'     => $status->first()->actor,
                'source'    => $status->first()->source,
                'at'        => $status->first()->created_at,
                'expired'   => $status->where('new_status', Job::STATUS_EXPIRED)->count(),
                'trashed'   => $status->where('new_status', Job::STATUS_TRASHED)->count(),
                'extended'  => $status->filter(fn ($r) => $r->old_status === $r->new_status)->count(),
            ],
        ]);
    }

    /**
     * Append a run summary to its own file. Production runs LOG_LEVEL=error, so
     * Log::info() would be discarded and we would collect nothing.
     */
    private function logBatch(string $batch, string $actor, array $plan,
                              int $extended, int $relinked, int $skipped): void
    {
        $file = storage_path('logs/link-check-' . date('Y-m') . '.log');
        $line = implode("\t", [
            date('c'), $batch, $actor,
            'rows=' . $plan['counts']['rows'],
            'extended=' . $extended,
            'relinked=' . $relinked,
            'skipped=' . $skipped,
            'review=' . $plan['counts']['review'],
            'wrong_target=' . $plan['counts']['wrongTarget'],
            'groups=' . $plan['counts']['groups'],
            'already_applied=' . $plan['counts']['alreadyApplied'],
        ]) . "\n";

        foreach ($plan['apply'] as $row) {
            $line .= implode("\t", [
                date('c'), $batch, $row['class'], $row['job_no'],
                $row['old_delete_at'] . ' -> ' . $row['new_delete_at'],
                $row['new_link'] !== $row['current_link']
                    ? $row['current_link'] . ' -> ' . $row['new_link'] : 'link unchanged',
            ]) . "\n";
        }

        try {
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::error('link-check log write failed', ['error' => $e->getMessage()]);
        }
    }

    private function recentBatches(): array
    {
        try {
            return DB::table('job_status_history')
                ->whereIn('source', ['link-check', 'link-check-action', 'bulk-expire'])
                ->select('batch', 'actor', 'source', DB::raw('COUNT(*) as n'), DB::raw('MAX(created_at) as at'))
                ->groupBy('batch', 'actor', 'source')
                ->orderByDesc('at')
                ->limit(10)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];   // table not migrated yet
        }
    }

    private function actorName(): string
    {
        $u = session('user');
        if (! $u) {
            return 'unknown';
        }
        return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ('user ' . ($u->id ?? '?'));
    }
}
