<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class, 'job_category_id');
    }

    public function activeJobs()
    {
        return $this->hasMany(Job::class, 'job_category_id')
                     ->where('job_status_id', Job::STATUS_PUBLISHED);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
