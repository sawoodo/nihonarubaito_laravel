<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
