<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * SystemPrompt - Model representing a dynamic AI prompt template.
 * 
 * Slugs are used as unique identifiers by the PromptService to fetch content.
 * The 'variables' field stores an array of available placeholders for the UI.
 */
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
