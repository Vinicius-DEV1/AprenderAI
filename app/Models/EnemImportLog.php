<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnemImportLog extends Model
{
    protected $fillable = [
        'year',
        'inserted_count',
        'ignored_count',
        'error_count',
        'errors',
        'status',
    ];

    protected $casts = [
        'errors' => 'array',
    ];
}
