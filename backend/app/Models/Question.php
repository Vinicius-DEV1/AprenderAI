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
        'difficulty',
        'difficulty_reasoning',
        'year',
        'statement',
        'explanation',
        'source',
        'organization',
        'institution',
        'role',
        'theme',
        'external_id',
        'review_status',
        'image_path',
        'tipo_questao',
        'number',
        'arquivo_origem',
        'discursive_answer',
    ];

    protected $casts = [
        'format' => 'string',
        'discursive_answer' => 'array',
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

    public function discursiveResponses()
    {
        return $this->hasMany(DiscursiveResponse::class);
    }

    public function isCorrect(string $answer): bool
    {
        return $this->alternatives()
            ->where('label', strtoupper($answer))
            ->where('is_correct', true)
            ->exists();
    }

    /**
     * Lógica N:N (Pivot): Get the subjects associated with the question.
     * This defines the Many-to-Many relationship using the 'question_subject' pivot table.
     * Isso substitui a antiga coluna textual 'subject', permitindo questões multidisciplinares.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }

    /**
     * Lógica N:N (Pivot): Get the topics associated with the question.
     * This defines the Many-to-Many relationship using the 'question_topic' pivot table.
     * Substitui a coluna textual legacy 'topic', externalizando metadados.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'question_topic');
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
                )->orWhereDoesntHave('subjects')
                ->orWhereDoesntHave('topics');
        });
    }

    public function isIncomplete(): bool
    {
        return empty(trim($this->difficulty_reasoning ?? ''))
            || empty(trim($this->explanation ?? ''))
            || $this->subjects()->doesntExist()
            || $this->topics()->doesntExist();
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

    /**
     * Retorna o enunciado processado para exibição em HTML.
     * 
     * Este accessor resolve dois problemas:
     * 1. Renderização de Imagens: Converte a sintaxe Markdown ![]() em tags <img> reais.
     * 2. Segurança: Escapa o conteúdo original com e() antes de processar as tags seguras.
     * 3. Formatação: Converte quebras de linha em <br> para preservar a estrutura do texto.
     * 
     * @return string
     */
    public function getStatementHtmlAttribute(): string
    {
        if (empty($this->statement)) {
            return '';
        }

        // 1. Escapar HTML para segurança contra XSS (mesmo comportamento do {{ }} no Blade)
        $html = e($this->statement);

        // 2. Processar Imagens Markdown: ![](URL) -> <img ...>
        // O regex busca a sintaxe Markdown de imagem e converte para uma tag <img>
        // com classes CSS pré-definidas para garantir boa exibição e centralização.
        $html = preg_replace(
            '/!\[(.*?)\]\((.*?)\)/',
            '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg my-4 mx-auto block shadow-sm" loading="lazy">',
            $html
        );

        // 3. Converter quebras de linha (\n) em tags HTML <br>
        return nl2br($html);
    }

    /**
     * Scope: filtra pelo tipo da questão (enem ou concurso)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $type
     */
    public function scopeFilterByType($query, ?string $type)
    {
        return $query->when($type, fn($q) => $q->where('type', $type));
    }

    /**
     * Scope: filtra por matéria (id)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $subjectId
     */
    public function scopeFilterBySubject($query, $subjectId)
    {
        return $query->when(
            $subjectId,
            fn($q) =>
            $q->whereHas('subjects', fn($s) => $s->where('subjects.id', $subjectId))
        );
    }

    /**
     * Scope: filtra por assunto/tópico (100% baseado na relação de pivô)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $topicId
     */
    public function scopeFilterByTopic($query, $topicId)
    {
        return $query->when($topicId, function ($q) use ($topicId) {
            $q->whereHas('topics', function ($subQ) use ($topicId) {
                $subQ->where('topics.id', $topicId);
            });
        });
    }

    /**
     * Get the images associated with the question.
     */
    public function images()
    {
        return $this->hasMany(QuestionImage::class);
    }
}
