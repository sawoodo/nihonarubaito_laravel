<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrappedDataFile extends Model
{
    protected $table = 'scrapped_data_files';
    public $timestamps = false;

    protected $fillable = [
        'name', 'client_name', 'lang_id', 'scrapped_data_file_type_id',
    ];

    public function fileType()
    {
        return $this->belongsTo(ScrappedDataFileType::class, 'scrapped_data_file_type_id');
    }
}
