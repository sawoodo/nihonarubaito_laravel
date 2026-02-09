<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Occupation extends Model
{
    protected $table = 'occupations';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'vietnamese', 'korean',
    ];

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
