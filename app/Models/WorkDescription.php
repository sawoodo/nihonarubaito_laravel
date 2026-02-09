<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkDescription extends Model
{
    protected $table = 'work_descriptions';
    public $timestamps = false;

    protected $fillable = [
        'work_id', 'chinese', 'english', 'vietnamese', 'japanese', 'korean',
    ];

    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
