<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prefecture extends Model
{
    protected $table = 'prefectures';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'korean', 'vietnamese', 'region_id',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function towns()
    {
        return $this->hasMany(Town::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function activeJobs()
    {
        return $this->hasMany(Job::class)->where('job_status_id', Job::STATUS_PUBLISHED);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }

    public function getSlugAttribute(): string
    {
        return strtolower(str_replace(' ', '-', $this->english));
    }
}
