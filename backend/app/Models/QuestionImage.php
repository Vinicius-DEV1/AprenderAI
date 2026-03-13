<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'path',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return '/storage/' . ltrim($this->path ?? '', '/');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
