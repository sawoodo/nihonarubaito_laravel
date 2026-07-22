<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_tag', function (Blueprint $table) {
            $table->integer('job_id');                        // PLAIN int — matches jobs.id exactly
            $table->integer('tag_id');
            $table->primary(['tag_id', 'job_id']);            // composite PK for "jobs for this tag"
            $table->index('job_id');                          // reverse lookup + join optimization

            // FK constraints for referential integrity + cascade
            // If errno 150 occurs, comment out these lines and keep indexed columns only
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });

        // Force utf8mb4
        DB::statement('ALTER TABLE job_tag CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_tag');
    }
};
