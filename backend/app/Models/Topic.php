<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    /**
     * AVISO CRÍTICO DE ARQUITETURA
     * -----------------------------
     * NUNCA adicione 'subject_id' nesta tabela ou no array $fillable.
     * Subjects e Topics são entidades independentes ("soltas") no banco de dados.
     * O relacionamento entre eles e as Questões é gerenciado EXCLUSIVAMENTE
     * via tabelas pivô independentes (`question_subject` e `question_topic`)
     * para permitir total flexibilidade e vinculações N:N limpas.
     */

    protected $fillable = [
        'name',
        'slug',
        'qdrant_indexed_at',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => mb_strtoupper(trim($value), 'UTF-8'),
        );
    }

    /**
     * Get the questions associated with the topic.
     */
    public function questions()
    {
        return $this->belongsToMany(Question::class, 'question_topic');
    }
}
