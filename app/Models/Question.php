<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'format',
        'theme',
        'difficulty',
        'year',
        'statement',
        'explanation',
        'source',
        'topic',
        'origin',
        'organization',
        'institution',
        'role',
        'external_id',
        'difficulty_reasoning',
    ];

    protected $casts = [
        'format' => 'string',
    ];

    public function alternatives()
    {
        return $this->hasMany(QuestionAlternative::class);
    }

    public function simulationAnswers()
    {
        return $this->hasMany(SimulationAnswer::class);
    }

    public function userAnswers()
    {
        return $this->hasMany(UserQuestionAnswer::class);
    }

    public function isCorrect(string $answer): bool
    {
        return $this->alternatives()
            ->where('label', strtoupper($answer))
            ->where('is_correct', true)
            ->exists();
    }

    /**
     * Get the subjects associated with the question.
     * This defines the Many-to-Many relationship using the 'question_subject' pivot table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }

    /**
     * Legacy Accessor for backward compatibility.
     * Returns the first subject name or the old column value.
     */
    public function getSubjectAttribute($value)
    {
        if ($this->relationLoaded('subjects') && $this->subjects->isNotEmpty()) {
            return $this->subjects->first()->name;
        }
        return $value;
    }

    /**
     * Scope: questões com QUALQUER campo pendente (para fila de triagem).
     */
    public function scopeIncomplete($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($sub) {
                    $sub->whereNull('difficulty_reasoning')
                        ->orWhereRaw("TRIM(difficulty_reasoning) = ''");
                }
                )->orWhere(function ($sub) {
                    $sub->whereNull('explanation')
                        ->orWhereRaw("TRIM(explanation) = ''");
                }
                );
            });
    }

    /**
     * Scope: questões 100% completas (para Banco Geral).
     */
    public function scopeComplete($query)
    {
        return $query->whereNotNull('difficulty_reasoning')
            ->whereRaw("TRIM(difficulty_reasoning) != ''")
            ->whereNotNull('explanation')
            ->whereRaw("TRIM(explanation) != ''");
    }

    /**
     * Scope: questões com um campo específico faltando.
     * @param string $field 'difficulty_reasoning' ou 'explanation'
     */
    public function scopeMissingField($query, string $field)
    {
        return $query->where(function ($q) use ($field) {
            $q->whereNull($field)->orWhereRaw("TRIM({$field}) = ''");
        });
    }

    public function getStatementHtmlAttribute(): string
    {
        if (empty($this->statement)) {
            return '';
        }

        // 1. Converter Imagens Markdown: ![](URL) -> <img ...>
        $html = preg_replace(
            '/!\[(.*?)\]\((.*?)\)/',
            '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" loading="lazy">',
            $this->statement
        );

        return $html;
    }
}
