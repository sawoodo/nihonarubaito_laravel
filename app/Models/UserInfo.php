<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    protected $table = 'user_info';
    public $timestamps = false;

    protected $fillable = [
        'age', 'gender', 'phone', 'country_id',
        'user_selected_lang', 'japanese_level', 'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function selectedLanguage()
    {
        return $this->belongsTo(Language::class, 'user_selected_lang');
    }
}
