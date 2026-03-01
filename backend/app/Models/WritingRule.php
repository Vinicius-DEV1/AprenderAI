<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WritingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'min_chars',
        'max_chars',
        'max_lines',
    ];
}
