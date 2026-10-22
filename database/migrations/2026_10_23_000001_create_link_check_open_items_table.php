<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work the link-check upload surfaced but did not do.
 *
 * The preview was ephemeral: on Apply the staged CSV was deleted and the
 * duplicate groups and staff-review rows vanished with it, so nobody could act
 * on them later. This table keeps them.
 *
 * ONE ROW PER JOB. A same-final-offer group is simply the set of rows sharing
 * a group_hash, which avoids a second table and lets a group shrink naturally
 * as its members are retired.
 *
 * item_key is a single UNIQUE column rather than a composite on
 * (job_no, group_hash): group_hash is NULL for review rows, and MySQL treats
 * NULLs as distinct in a unique index, so a composite would silently allow
 * duplicate review rows. On Apply we upsert on item_key and clear resolved_at,
 * so an item that recurs in a later scan reopens rather than duplicating.
 *
 * Resolution is SOFT. Rows are never deleted, so "who cleared this, how, and
 * when" survives — the same reasoning that produced job_status_history after
 * 2026-10-21, when "how was this job retired?" had no answer anywhere.
 *
 * Every value column is nullable on purpose: MySQL here runs without strict
 * mode, so a NOT NULL column would not error on an omitted value, it would
 * coerce to ''/0 and look like real data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_check_open_items', function (Blueprint $table) {
            $table->id();

            // 'review:<job_no>' or 'group:<hash>:<job_no>'
            $table->string('item_key', 96)->unique();
            $table->string('kind', 12)->nullable()->index();      // review | group
            $table->string('job_no', 20)->index();

            $table->string('group_hash', 64)->nullable()->index();
            $table->text('final_url')->nullable();
            $table->string('page_title', 255)->nullable();
            $table->text('requested_url')->nullable();
            $table->string('classification', 20)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('source_checked_at')->nullable();

            $table->string('first_seen_batch', 64)->nullable();
            $table->string('last_seen_batch', 64)->nullable();

            // NULL = still open. Indexed because every page load filters on it.
            $table->timestamp('resolved_at')->nullable()->index();
            $table->string('resolved_by', 100)->nullable();
            $table->string('resolved_action', 40)->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_check_open_items');
    }
};
