<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fb_posts', function (Blueprint $table) {
            // Nullable: posts whose job was later hard-deleted keep NULL and simply
            // do not appear under any prefecture filter. No FK constraint — jobs are
            // deleted independently and a constraint would block that.
            $table->unsignedInteger('prefecture_id')->nullable()->after('lang_id');
            $table->index('prefecture_id', 'fb_posts_prefecture_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('fb_posts', function (Blueprint $table) {
            $table->dropIndex('fb_posts_prefecture_id_index');
            $table->dropColumn('prefecture_id');
        });
    }
};
