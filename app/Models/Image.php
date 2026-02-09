<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'images';
    public $timestamps = false;

    protected $fillable = [
        'path', 'name', 'ext', 'title', 'description',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class, 'img_id');
    }
}
