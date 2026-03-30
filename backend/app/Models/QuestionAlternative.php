<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionAlternative extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'label',
        'content',
        'image_path',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    /**
     * Helper to gracefully downgrade AI-generated tables into readable text.
     */
    private function gracefullyDowngradeTables(?string $value): ?string
    {
        if (empty($value) || !str_contains(strtolower($value), '<table')) {
            return $value;
        }

        // Convert rows to line breaks, cols to separators
        $value = preg_replace('/<\/tr>/i', "<br>\n", $value);
        $value = preg_replace('/<\/td>/i', " &nbsp;&nbsp;|&nbsp;&nbsp; ", $value);
        $value = preg_replace('/<\/th>/i', " &nbsp;&nbsp;|&nbsp;&nbsp; ", $value);
        
        // Remove all table tags
        $tableTags = ['table', 'tbody', 'thead', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col', 'caption'];
        foreach ($tableTags as $tag) {
            $value = preg_replace("/<\/?{$tag}[^>]*>/i", "", $value);
        }

        return $value;
    }

    public function setContentAttribute($value)
    {
        $this->attributes['content'] = $this->gracefullyDowngradeTables($value);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
