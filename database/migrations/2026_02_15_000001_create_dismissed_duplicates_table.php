<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissed_duplicates', function (Blueprint $table) {
            $table->id();
            $table->string('group_hash', 64)->unique();
            $table->unsignedInteger('dismissed_by');
            $table->timestamp('dismissed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissed_duplicates');
    }
};
