<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobLocationPreference extends Model
{
    protected $table = 'job_location_preferences';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'prefecture_id', 'area_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prefecture()
    {
        return $this->belongsTo(Prefecture::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
