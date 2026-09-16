<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbPost extends Model
{
    protected $table = 'fb_posts';
    public $timestamps = false;

    protected $fillable = [
        'link', 'content', 'lang_id', 'prefecture_id', 'published',
        'created_at', 'scheduled_at', 'run_at', 'run_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'run_at' => 'datetime',
        'published' => 'boolean',
    ];

    public function language()
    {
        return $this->belongsTo(Language::class, 'lang_id');
    }
}
