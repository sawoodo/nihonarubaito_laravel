<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $table = 'job_status';
    public $timestamps = false;

    protected $fillable = [
        'status',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class, 'job_status_id');
    }
}
