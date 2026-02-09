<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCategoryPreference extends Model
{
    protected $table = 'job_category_preferences';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'job_category_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'job_category_id');
    }
}
