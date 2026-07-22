<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriberPreference extends Model
{
    protected $table = 'subscriber_preferences';

    protected $fillable = [
        'user_id',
        'area_ids',
        'commute_neighboring',
        'wants_monthly_transfer',
        'wants_daily_payment',
        'wants_hand_cash',
        'shift_morning',
        'shift_afternoon',
        'shift_evening',
        'shift_night',
        'shift_any',
        'visa_type',
        'japanese_level',
        'max_hours_per_week',
        'min_wage',
        'alert_frequency',
        'alert_hand_cash',
        'alert_high_wage',
        'last_alert_at',
    ];

    protected $casts = [
        'area_ids' => 'array',
        'commute_neighboring' => 'boolean',
        'wants_monthly_transfer' => 'boolean',
        'wants_daily_payment' => 'boolean',
        'wants_hand_cash' => 'boolean',
        'shift_morning' => 'boolean',
        'shift_afternoon' => 'boolean',
        'shift_evening' => 'boolean',
        'shift_night' => 'boolean',
        'shift_any' => 'boolean',
        'alert_hand_cash' => 'boolean',
        'alert_high_wage' => 'boolean',
        'last_alert_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
