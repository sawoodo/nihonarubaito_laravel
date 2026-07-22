<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ga4_landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_path', 500);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index(['date_from', 'date_to']);
            $table->index('page_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ga4_landing_pages');
    }
};
