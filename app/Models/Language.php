<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'languages';
    public $timestamps = false;

    protected $fillable = [
        'language', 'chinese', 'english', 'vietnamese', 'japanese', 'korean', 'flag_path',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class, 'lang_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'lang_id');
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
