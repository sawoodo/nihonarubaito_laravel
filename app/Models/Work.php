<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    protected $table = 'work';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'vietnamese', 'japanese', 'korean',
    ];

    public function descriptions()
    {
        return $this->hasMany(WorkDescription::class);
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
