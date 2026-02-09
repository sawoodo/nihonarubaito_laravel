<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Town extends Model
{
    protected $table = 'towns';
    public $timestamps = false;

    protected $fillable = [
        'prefecture_id', 'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }

    public function areas()
    {
        return $this->hasMany(Area::class);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
