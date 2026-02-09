<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransExpPayment extends Model
{
    protected $table = 'trans_exp_payments';
    public $timestamps = false;

    protected $fillable = [
        'chinese', 'english', 'japanese', 'korean', 'vietnamese',
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class, 'trans_exp_id');
    }

    public function getNameAttribute(): string
    {
        return $this->english;
    }
}
