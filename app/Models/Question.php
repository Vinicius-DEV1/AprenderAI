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
        'difficulty_reasoning',
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
        'review_status',
        'image_path',
    ];

    protected $casts = [
        'format' => 'string',
    ];

    public function alternatives()
    {
        return $this->hasMany(QuestionAlternative::class);
    }

    /**
     * Accessor de compatibilidade: retorna a letra do gabarito (ex: 'C').
     *
     * MOTIVO: A coluna `correct_answer` foi removida da tabela `questions`
     * quando as alternativas migraram para a tabela `question_alternatives`.
     * O gabarito agora é determinado por `is_correct = true` na tabela relacional.
     *
     * Código legado que usa `$question->correct_answer` continua funcionando
     * sem precisar ser alterado, desde que a relação alternatives esteja carregada.
     */
    public function getCorrectAnswerAttribute(): ?string
    {
        // Usa getRelation() para acessar diretamente a coleção já carregada,
        // evitando conflito com coluna de mesmo nome na tabela.
        if ($this->relationLoaded('alternatives')) {
            $collection = $this->getRelation('alternatives');
            if ($collection !== null) {
                $correct = $collection->firstWhere('is_correct', true);
                return $correct?->label;
            }
        }
        // Fallback: faz uma query pontual se a relação não estiver em memória
        return $this->alternatives()->where('is_correct', true)->value('label');
    }

    /**
     * Formata as alternativas como mapa associativo para uso em prompts de IA.
     *
     * PROBLEMA RESOLVIDO:
     * json_encode($question->alternatives) serializa uma Collection de objetos
     * QuestionAlternative (com id, question_id, label, content, is_correct...),
     * gerando um JSON verboso e confuso para os LLMs.
     *
     * RETORNO ESPERADO:
     * ["A" => "Texto da alternativa A", "B" => "Texto da alternativa B", ...]
     *
     * @return array<string, string>  ['A' => '...', 'B' => '...', ...]
     */
    public function alternativesAsMap(): array
    {
        $alts = $this->relationLoaded('alternatives')
            ? $this->alternatives
            : $this->alternatives()->get();

        return $alts->pluck('content', 'label')->toArray();
    }

    /**
     * Item de auditoria desta questão no módulo de importação.
     * Permite saber quem aprovou, quando, e em qual lote a questão foi importada.
     */
    public function importItem()
    {
        return $this->hasOne(\App\Models\QuestionImportItem::class);
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
            $q->where(
                function ($sub) {
                    $sub->whereNull('difficulty_reasoning')
                        ->orWhereRaw("TRIM(difficulty_reasoning) = ''");
                }
            )->orWhere(
                    function ($sub) {
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
     * Scope: filtra questões visíveis para os alunos no banco público.
     * Exclui questões importadas que ainda estão pendentes de revisão.
     * Questões sem review_status (criadas manualmente) são sempre visíveis.
     */
    public function scopePublished($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('review_status')       // Questões manuais (pré-importador)
              ->orWhere('review_status', 'approved'); // Questões importadas e aprovadas
        });
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
