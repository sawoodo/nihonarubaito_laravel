<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change log for jobs.
 *
 * On 2026-10-21 the question "how was this job retired?" had no answer: there
 * is no status or field history anywhere in this schema, `dismissed_duplicates`
 * is empty, and `updated_by` is the same account that created every job. This
 * table is where that answer will live from now on.
 *
 * Every column except the identity is NULLABLE ON PURPOSE. The jobs table has
 * 24 NOT NULL columns with no default and MySQL here runs WITHOUT strict mode
 * (sql_mode = NO_ENGINE_SUBSTITUTION), so a NOT NULL column would not error on
 * an omitted value -- it would silently coerce to ''/0 and look like real data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_status_history', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 20)->index();

            // Status may be unchanged (a delete_at extension); hence nullable.
            $table->tinyInteger('old_status')->nullable();
            $table->tinyInteger('new_status')->nullable();

            $table->timestamp('old_delete_at')->nullable();
            $table->timestamp('new_delete_at')->nullable();

            // 'link-check', 'xml-import', 'admin', 'auto-trash', ...
            $table->string('source', 50)->nullable()->index();
            $table->string('actor', 100)->nullable();
            $table->string('reason', 255)->nullable();
            // Groups every row written by one upload, so a run can be undone.
            $table->string('batch', 64)->nullable()->index();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_status_history');
    }
};
