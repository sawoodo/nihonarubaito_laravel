<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbPostedLog extends Model
{
    use HasFactory;

    protected $table = 'fb_posted_log';

    protected $fillable = [
        'job_no',
        'page',
        'posted_at',
        'post_format',
        'was_boosted',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'was_boosted' => 'boolean',
    ];
}
