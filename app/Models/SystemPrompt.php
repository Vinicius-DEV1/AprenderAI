<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemPrompt extends Model
{
    /** @use HasFactory<\Database\Factories\SystemPromptFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'variables',
    ];

    protected $casts = [
        'variables' => 'array',
    ];
}
