<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $table = 'job_applications';
    public $timestamps = false;

    protected $fillable = [
        'first_name', 'last_name', 'gender', 'date_of_birth',
        'occupation_id', 'email', 'phone', 'postal_code',
        'prefecture_id', 'area_id', 'job_no',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_no', 'job_no');
    }

    public function occupation()
    {
        return $this->belongsTo(Occupation::class);
    }

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
