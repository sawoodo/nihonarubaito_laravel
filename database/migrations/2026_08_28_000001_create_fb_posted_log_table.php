<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_posted_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_no')->index();
            $table->string('page');            // 'tokyo' | 'kanto' | 'osaka'
            $table->timestamp('posted_at')->useCurrent();
            $table->string('post_format')->nullable();   // 'text' | 'link'
            $table->boolean('was_boosted')->default(false);
            $table->timestamps();
            $table->index(['job_no', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_posted_log');
    }
};
