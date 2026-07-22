<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_preferences', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');

            // -- Location --
            $table->json('area_ids')->nullable();
            $table->boolean('commute_neighboring')->default(false);

            // -- Payment Preferences --
            $table->boolean('wants_monthly_transfer')->default(true);
            $table->boolean('wants_daily_payment')->default(false);
            $table->boolean('wants_hand_cash')->default(false);

            // -- Schedule --
            $table->boolean('shift_morning')->default(false);
            $table->boolean('shift_afternoon')->default(false);
            $table->boolean('shift_evening')->default(false);
            $table->boolean('shift_night')->default(false);
            $table->boolean('shift_any')->default(true);

            // -- Personal Info --
            $table->string('visa_type', 30)->nullable();
            $table->string('japanese_level', 20)->nullable();
            $table->unsignedTinyInteger('max_hours_per_week')->nullable();
            $table->unsignedSmallInteger('min_wage')->nullable();

            // -- Notification Preferences --
            $table->string('alert_frequency', 15)->default('weekly');
            $table->boolean('alert_hand_cash')->default(false);
            $table->boolean('alert_high_wage')->default(false);
            $table->timestamp('last_alert_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_preferences');
    }
};
