<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JobMatchApplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The buttons on the duplicate card.
 *
 * They are POSTs of their own rather than part of the import form, because the
 * import form creates a job and these must not: the whole point is that no
 * second copy is created. The translator's pasted XML is discarded.
 *
 * Relinking is admins and managers only. The 'admin' middleware admits
 * advertisers too (User::isBackendUser covers roles 1, 2 and 4), and an
 * advertiser has no business rewriting the apply link of a job that may not be
 * theirs. Discarding has no role gate: it changes nothing.
 */
class JobMatchController extends Controller
{
    public function relink(Request $request)
    {
        $data = $request->validate([
            'job_no' => 'required|string|max:20',
            'new_link' => 'required|string|max:2048',
            'delete_in_days' => 'required|integer|min:1|max:365',
            'return_to' => 'nullable|in:xml,manual',
            // What the card showed. The write is refused if the row no longer
            // matches these, so a stale card cannot overwrite someone's edit.
            'expect_status' => 'required|integer|min:1|max:6',
            'expect_link' => 'nullable|string|max:8192',
        ]);

        $user = session('user');

        if (! $this->mayRelink($user)) {
            return redirect('/admin/jobs')
                ->with('error', 'Only an admin or manager can change a job\'s apply link.');
        }

        $actor = $user->email ?? ('user:' . ($user->id ?? '?'));

        $result = JobMatchApplier::relink(
            $data['job_no'],
            $data['new_link'],
            (int) $data['delete_in_days'],
            (int) $data['expect_status'],
            (string) ($data['expect_link'] ?? ''),
            'xml-import',
            $actor
        );

        if (! $result['ok']) {
            return redirect($this->importForm($data['return_to'] ?? 'xml'))->with('error', $result['message']);
        }

        return redirect("/admin/jobs/{$data['job_no']}/edit")
            ->with('success', $result['message']);
    }

    /** "Update job N with this link and trash this one", at the publish check. */
    public function relinkAndTrash(Request $request)
    {
        $data = $request->validate([
            'job_no' => 'required|string|max:20',
            'duplicate_job_no' => 'required|string|max:20',
            'expect_status' => 'required|integer|min:1|max:6',
            'expect_link' => 'nullable|string|max:8192',
            'expect_duplicate_link' => 'nullable|string|max:8192',
        ]);

        $user = session('user');

        if (! $this->mayRelink($user)) {
            return redirect('/admin/jobs')
                ->with('error', 'Only an admin or manager can change a job\'s apply link.');
        }

        $result = JobMatchApplier::relinkAndTrash(
            $data['job_no'],
            $data['duplicate_job_no'],
            (int) $data['expect_status'],
            (string) ($data['expect_link'] ?? ''),
            (string) ($data['expect_duplicate_link'] ?? ''),
            $user->email ?? ('user:' . ($user->id ?? '?'))
        );

        if (! $result['ok']) {
            return redirect("/admin/jobs/{$data['duplicate_job_no']}/edit")
                ->with('error', $result['message']);
        }

        return redirect("/admin/jobs/{$data['job_no']}/edit")
            ->with('success', $result['message']);
    }

    /**
     * "Discard": the translator agrees it is a duplicate and wants out. Writes
     * nothing to jobs -- no job exists yet -- and logs the decision, because
     * until now the only way to do this was to leave the page, which left no
     * trace that the check had saved a duplicate.
     */
    public function discard(Request $request)
    {
        $data = $request->validate([
            'matched_job_no' => 'required|string|max:20',
            'form' => 'required|in:xml,manual',
            'apply_link' => 'nullable|string|max:8192',
        ]);

        $user = session('user');

        DB::table('job_match_discards')->insert([
            'user_id' => $user->id ?? null,
            'actor' => $user->email ?? ('user:' . ($user->id ?? '?')),
            'matched_job_no' => $data['matched_job_no'],
            'form' => $data['form'],
            'apply_link' => $data['apply_link'] ?? null,
            'created_at' => now(),
        ]);

        $request->session()->forget(['job_matches', '_old_input']);

        return redirect($this->importForm($data['form']))->with('success', 'Nothing was saved.');
    }

    private function mayRelink($user): bool
    {
        return in_array((int) ($user->role_id ?? 0), [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    private function importForm(string $form): string
    {
        return $form === 'manual' ? '/admin/jobs/create' : '/admin/jobs/create-from-xml';
    }
}
