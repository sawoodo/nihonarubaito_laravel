<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Previous apply_link values for a job.
 *
 * shigotoin rotates offer ids: an old /offer/<id> 301s to a new one, and the
 * same underlying employer job can hold several live ids at once. Measured
 * 2026-10-21: 8 of 11 published control jobs had already moved. When the
 * link-check rewrites apply_link to the resolved final URL we keep the old one
 * here, so a job's link lineage is recoverable and two of our jobs collapsing
 * onto one final offer stays visible after the fact.
 *
 * All value columns nullable -- see the note in the job_status_history
 * migration about non-strict sql_mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_apply_link_history', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 20)->index();

            $table->text('old_apply_link')->nullable();
            $table->text('new_apply_link')->nullable();

            // What the scan observed, kept for auditing the rewrite.
            $table->string('http_code', 8)->nullable();
            $table->unsignedSmallInteger('num_redirects')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->string('source', 50)->nullable()->index();
            $table->string('actor', 100)->nullable();
            $table->string('batch', 64)->nullable()->index();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_apply_link_history');
    }
};
