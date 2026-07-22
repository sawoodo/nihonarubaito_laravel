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
        Schema::create('tags', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();          // plain int to match jobs.id
            $table->string('name', 100);
            $table->string('slug', 120)->unique();           // unique index for landing-page lookup
            $table->boolean('has_landing_page')->default(false);
            $table->integer('impressions')->nullable();      // GSC impression tracking
            $table->text('description')->nullable();         // landing-page content
            $table->timestamps();
        });

        // Force utf8mb4 for Japanese tag names
        DB::statement('ALTER TABLE tags CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
