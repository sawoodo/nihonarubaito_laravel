<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add slug column to areas table.
 *
 * PROBLEM: findAreaByName() uses LIKE "%slug%" (substring match), causing
 * 175 of 1,892 areas to resolve to DIFFERENT areas. Example: "ito" matches
 * "Taito", "hino" matches "Musashino". These areas have no working URL.
 *
 * FIX: Store unique slug per area, look up by exact match first, keep fuzzy
 * fallback for ~1,183 canonicalizations (/shinjuku -> 301 -> /shinjuku-ward).
 *
 * SAFETY: Nullable, no unique constraint yet. Unique index added only after
 * populate command proves all values are distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            // Nullable and NOT unique yet — the unique index goes on only
            // after the populate command proves every value is distinct.
            $table->string('slug', 191)->nullable()->after('english');
            $table->index('slug', 'areas_slug_index');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex('areas_slug_index');
            $table->dropColumn('slug');
        });
    }
};
