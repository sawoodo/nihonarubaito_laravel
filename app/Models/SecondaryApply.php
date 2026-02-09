<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecondaryApply extends Model
{
    protected $table = 'secondary_applies';
    public $timestamps = false;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'job_no', 'apply_date',
    ];

    protected $casts = [
        'apply_date' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_no', 'job_no');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
