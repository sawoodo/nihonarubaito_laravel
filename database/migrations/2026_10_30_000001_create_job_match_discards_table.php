<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row each time someone pressed "Discard" on the import duplicate card.
 *
 * job_status_history is keyed on a job and a discard has no job: the point of
 * the button is that none was created. It also gives us the count of how often
 * the check saves a duplicate, which nothing measured before.
 *
 * All value columns nullable -- see the note in the job_status_history
 * migration about non-strict sql_mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_match_discards', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->integer('user_id')->nullable()->index();
            $table->string('actor', 100)->nullable();

            // The first certain match on the card, and which form it was on.
            $table->string('matched_job_no', 20)->nullable()->index();
            $table->string('form', 20)->nullable();
            $table->text('apply_link')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_match_discards');
    }
};
