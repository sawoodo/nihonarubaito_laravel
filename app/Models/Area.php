<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    public $timestamps = false;

    protected $fillable = [
        'town_id', 'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function town()
    {
        return $this->belongsTo(Town::class);
    }

    public function prefecture()
    {
        return $this->town ? $this->town->prefecture() : null;
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
