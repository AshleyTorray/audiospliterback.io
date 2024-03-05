<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcelAudioLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'accounting_day',
        'order_no',
        'precek',
        'time',
        'closed',
        'waiter',
        'file_path',
        'file_name'
    ];
}
