<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
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
 * AdminMiddleware is session-only (session('loggedin') + isBackendUser()); there
 * is no routes/api.php and no token auth. A machine-callable endpoint would mean
 * inventing a token scheme, storing a secret on the Mac and adding a CSRF
 * exception -- new public write surface on a site that had two exposure
 * incidents in October. An upload costs one click and adds none.
 *
 * WHAT IT WILL AND WILL NOT DO
 *   OPEN   -> delete_at += 7. The job is still live; keep it listed.
 *   MOVED  -> apply_link rewritten to the resolved final URL, old value kept in
 *             job_apply_link_history, delete_at += 7.
 *   CLOSED / GONE / ERROR -> listed for staff, NEVER written, never auto-expired.
 *             An ERROR means the check failed, not that the job did, and a
 *             classifier allowed to retire inventory on its own is a classifier
 *             that will eventually retire it wrongly.
 *
 * Two of our jobs resolving to the SAME final offer are flagged and excluded
 * from the automatic path -- that is a duplicate for a human to settle, not a
 * link to rewrite.
 *
 * Nothing here is applied on upload. Upload previews; a second, explicit
 * confirmation writes.
 */
class LinkCheckController extends Controller
{
    /** Classifications this page is willing to act on. */
    private const ACTIONABLE = ['OPEN', 'MOVED'];

    /** Days added to delete_at for a job confirmed still live. */
    private const EXTEND_DAYS = 7;

    private const STAGE_DIR = 'link-check';

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

        return view('admin.analytics.link-check', [
            'activeSideMenu' => 'link_check',
            'preview'        => null,
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

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $path = $request->file('csv_file')->getRealPath();
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
        $stored = storage_path('app/' . self::STAGE_DIR);
        if (! is_dir($stored)) {
            mkdir($stored, 0775, true);
        }
        copy($path, $stored . '/' . $token . '.csv');

        $plan = $this->buildPlan($rows);

        return view('admin.analytics.link-check', [
            'activeSideMenu' => 'link_check',
            'preview'        => $plan + ['token' => $token],
            'recent'         => $this->recentBatches(),
        ]);
    }

    /**
     * Stage 2: apply. Re-reads the staged file and re-derives the plan, so a
     * row that changed between preview and apply is re-evaluated, not replayed.
     */
    public function apply(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate(['token' => 'required|string|max:64']);
        $token = preg_replace('/[^a-z0-9\-]/i', '', (string) $request->input('token'));
        $file = storage_path('app/' . self::STAGE_DIR . '/' . $token . '.csv');

        if ($token === '' || ! is_file($file)) {
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
                // If either moved, this row is stale -- skip it, do not guess.
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

                DB::table('job_status_history')->insert([
                    'job_no'        => $row['job_no'],
                    'old_status'    => Job::STATUS_PUBLISHED,
                    'new_status'    => Job::STATUS_PUBLISHED,
                    'old_delete_at' => $row['old_delete_at'],
                    'new_delete_at' => $row['new_delete_at'],
                    'source'        => 'link-check',
                    'actor'         => $actor,
                    'reason'        => $row['class'] === 'MOVED'
                        ? 'still live at a new offer id; link rewritten, delete_at +' . self::EXTEND_DAYS
                        : 'confirmed still live; delete_at +' . self::EXTEND_DAYS,
                    'batch'         => $batch,
                    'created_at'    => now(),
                ]);

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

        $this->logBatch($batch, $actor, $plan, $extended, $relinked, $skipped);

        @unlink($file);

        $msg = "{$extended} job(s) extended by " . self::EXTEND_DAYS . " days"
             . ($relinked ? ", {$relinked} apply link(s) rewritten" : '')
             . ($skipped ? ", {$skipped} skipped (changed since the scan)" : '')
             . ". Batch {$batch}.";

        return redirect()->route('admin.analytics.link-check')->with('success', $msg);
    }

    // ──────────────────────────────────────────────────────────────────────

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
        $byJobNo = [];
        foreach ($rows as $r) {
            $byJobNo[trim((string) $r['job_no'])] = $r;
        }

        $jobs = DB::table('jobs')
            ->whereIn('job_no', array_keys($byJobNo))
            ->select('job_no', 'title', 'job_status_id', 'apply_link', 'delete_at')
            ->get()
            ->keyBy('job_no');

        // Two of our jobs landing on one final offer is a duplicate, not a link fix.
        $finalCounts = [];
        foreach ($byJobNo as $jobNo => $r) {
            if (! in_array($r['classification'], self::ACTIONABLE, true)) {
                continue;
            }
            $final = trim((string) $r['final_url']);
            if ($final === '') {
                continue;
            }
            $finalCounts[$final][] = $jobNo;
        }

        $apply = $review = $collisions = $notFound = $notPublished = [];

        foreach ($byJobNo as $jobNo => $r) {
            $class = $r['classification'];
            $job = $jobs[$jobNo] ?? null;

            if (! $job) {
                $notFound[] = ['job_no' => $jobNo, 'class' => $class];
                continue;
            }

            if (! in_array($class, self::ACTIONABLE, true)) {
                $review[] = [
                    'job_no' => $jobNo,
                    'class'  => $class,
                    'title'  => $job->title,
                    'url'    => $r['requested_url'],
                    'status' => (int) $job->job_status_id,
                    'note'   => $r['note'] ?? '',
                ];
                continue;
            }

            if ((int) $job->job_status_id !== Job::STATUS_PUBLISHED) {
                $notPublished[] = ['job_no' => $jobNo, 'class' => $class,
                                   'status' => (int) $job->job_status_id, 'title' => $job->title];
                continue;
            }

            $final = trim((string) $r['final_url']);
            $group = $finalCounts[$final] ?? [];
            if (count($group) > 1) {
                $collisions[] = ['job_no' => $jobNo, 'final' => $final,
                                 'others' => array_values(array_diff($group, [$jobNo])),
                                 'title' => $job->title];
                continue;
            }

            // Stale row: the stored link is no longer what the scan fetched.
            if (trim((string) $job->apply_link) !== trim((string) $r['requested_url'])) {
                $review[] = [
                    'job_no' => $jobNo, 'class' => $class, 'title' => $job->title,
                    'url' => $r['requested_url'], 'status' => (int) $job->job_status_id,
                    'note' => 'apply_link changed since the scan — re-scan this job',
                ];
                continue;
            }

            $old = $job->delete_at;
            $base = $old ? strtotime($old) : time();
            $new = date('Y-m-d H:i:s', $base + self::EXTEND_DAYS * 86400);

            $apply[] = [
                'job_no'        => $jobNo,
                'class'         => $class,
                'title'         => $job->title,
                'current_link'  => trim((string) $job->apply_link),
                'new_link'      => $final !== '' ? $final : trim((string) $job->apply_link),
                'old_delete_at' => $old,
                'new_delete_at' => $new,
                'http_code'     => $r['http_code'] ?? null,
                'num_redirects' => is_numeric($r['num_redirects'] ?? null) ? (int) $r['num_redirects'] : null,
                'checked_at'    => ! empty($r['checked_at']) ? date('Y-m-d H:i:s', strtotime($r['checked_at'])) : null,
            ];
        }

        usort($apply, fn ($a, $b) => [$a['class'], $a['job_no']] <=> [$b['class'], $b['job_no']]);

        return [
            'apply'        => $apply,
            'review'       => $review,
            'collisions'   => $collisions,
            'notFound'     => $notFound,
            'notPublished' => $notPublished,
            'counts'       => [
                'rows'         => count($byJobNo),
                'apply'        => count($apply),
                'moved'        => count(array_filter($apply, fn ($x) => $x['class'] === 'MOVED')),
                'open'         => count(array_filter($apply, fn ($x) => $x['class'] === 'OPEN')),
                'review'       => count($review),
                'collisions'   => count($collisions),
                'notFound'     => count($notFound),
                'notPublished' => count($notPublished),
            ],
        ];
    }

    /**
     * Append a run summary to its own file. Production runs LOG_LEVEL=error, so
     * Log::info() would be discarded and we would collect nothing.
     */
    private function logBatch(string $batch, string $actor, array $plan, int $extended, int $relinked, int $skipped): void
    {
        $file = storage_path('logs/link-check-' . date('Y-m') . '.log');
        $line = implode("\t", [
            date('c'), $batch, $actor,
            'rows=' . $plan['counts']['rows'],
            'extended=' . $extended,
            'relinked=' . $relinked,
            'skipped=' . $skipped,
            'review=' . $plan['counts']['review'],
            'collisions=' . $plan['counts']['collisions'],
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
                ->where('source', 'link-check')
                ->select('batch', 'actor', DB::raw('COUNT(*) as n'), DB::raw('MAX(created_at) as at'))
                ->groupBy('batch', 'actor')
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
