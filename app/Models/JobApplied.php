<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplied extends Model
{
    protected $table = 'jobs_applied';
    public $timestamps = false;

    protected $fillable = [
        'job_id', 'job_no', 'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
