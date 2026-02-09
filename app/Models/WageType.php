<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WageType extends Model
{
    protected $table = 'wage_types';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'vietnamese', 'korean',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
