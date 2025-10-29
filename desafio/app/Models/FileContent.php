<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileContent extends Model
{
    protected $table = 'file_contents';

    protected $fillable = [
        'RptDt',
        'TckrSymb',
        'MktNm',
        'SctyCtgyNm',
        'ISIN',
        'CrpnNm'
    ];

    public $timestamps = true;
}
