<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\JobMatchApplier;
use Illuminate\Http\Request;

/**
 * The "Update job N with this link" button on the import duplicate card.
 *
 * It is a POST of its own rather than part of the import form, because the
 * import form creates a job and this one must not: the whole point is that no
 * second copy is created. The translator's pasted XML is discarded.
 *
 * Admins and managers only. The 'admin' middleware admits advertisers too
 * (User::isBackendUser covers roles 1, 2 and 4), and an advertiser has no
 * business rewriting the apply link of a job that may not be theirs.
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

        if (! in_array((int) ($user->role_id ?? 0), [User::ROLE_ADMIN, User::ROLE_MANAGER], true)) {
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
            $back = ($data['return_to'] ?? 'xml') === 'manual'
                ? '/admin/jobs/create'
                : '/admin/jobs/create-from-xml';

            return redirect($back)->with('error', $result['message']);
        }

        return redirect("/admin/jobs/{$data['job_no']}/edit")
            ->with('success', $result['message']);
    }
}
