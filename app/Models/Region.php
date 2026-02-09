<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $table = 'regions';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function prefectures()
    {
        return $this->hasMany(Prefecture::class);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
