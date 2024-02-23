<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioFile extends Model
{
    use HasFactory;

    public $timestamps = true;
    protected $fillable = [
        'file_path',
        'file_name',
        'duration',
        'file_size',
        'format',
    ];
}
