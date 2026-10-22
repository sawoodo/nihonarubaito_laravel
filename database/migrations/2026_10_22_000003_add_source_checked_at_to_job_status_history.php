<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the scan result behind this change was observed.
 *
 * The link-check apply guards on status + apply_link. That makes a re-upload a
 * no-op for MOVED rows — their link was rewritten, so they no longer match the
 * CSV — but NOT for OPEN rows, whose link never changes. Re-uploading the same
 * results file would have silently added another 7 days to 588 jobs.
 *
 * Recording the scan's own `checked_at` here lets the planner skip any job
 * already extended from a result at or newer than the one being uploaded, so
 * the same file can be applied twice with no effect.
 *
 * Nullable: manual Extend/Expire actions have no scan behind them, and every
 * row written before this migration has no value to backfill. MySQL here runs
 * without strict mode, so a NOT NULL column would silently coerce instead of
 * erroring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_status_history', function (Blueprint $table) {
            $table->timestamp('source_checked_at')->nullable()->after('batch');
            $table->index(['job_no', 'source_checked_at'], 'jsh_job_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('job_status_history', function (Blueprint $table) {
            $table->dropIndex('jsh_job_checked_idx');
            $table->dropColumn('source_checked_at');
        });
    }
};
