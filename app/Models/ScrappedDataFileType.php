<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrappedDataFileType extends Model
{
    protected $table = 'scrapped_data_file_types';
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function files()
    {
        return $this->hasMany(ScrappedDataFile::class, 'scrapped_data_file_type_id');
    }
}
