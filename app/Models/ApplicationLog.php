<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationLog extends Model
{
    protected $table = 'application_logs';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'merchant_name', 'click_date', 'order_date',
        'job_no', 'expired',
    ];

    protected $casts = [
        'click_date' => 'datetime',
        'order_date' => 'datetime',
        'expired' => 'boolean',
    ];
}
