<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopularArea extends Model
{
    protected $table = 'popular_areas';
    protected $primaryKey = 'area_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'area_id',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
