<?php

namespace App\Jobs;

use App\Models\AiSearchRequest;
use App\Services\AI\AIService;
use App\Services\QuestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InterpretSearchPromptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $searchRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(AiSearchRequest $searchRequest)
    {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService, QuestionService $questionService, \App\Services\AI\SemanticCacheService $cacheService): void
    {
        try {
            $userPrompt = trim(strtolower($this->searchRequest->prompt));

            // [Nível 1] Busca Exata (Hash MD5) - Instantâneo
            $cachedFilters = $cacheService->findExactMatch($userPrompt);
            if ($cachedFilters) {
                $this->searchRequest->update(['filters' => $cachedFilters, 'status' => 'completed']);
                return;
            }

            // [Nível 2] Busca por Similaridade (Embeddings)
            // Gera a representação vetorial da frase
            $vector = $aiService->generateEmbedding($userPrompt);
            if ($vector) {
                $similarFilters = $cacheService->findSimilarMatch($vector, 0.94); // >94% de match
                if ($similarFilters) {
                    $this->searchRequest->update(['filters' => $similarFilters, 'status' => 'completed']);
                    return;
                }
            }

            // [Fallback] Ação original do Xavier
            $filterOptions = $questionService->getFilterOptions();
            $filters = $aiService->interpretSearchPrompt($this->searchRequest->prompt, $filterOptions);

            // Validação mínima: Consideramos sucesso se houver filtros reais OU sugestões de caminhos alternativos
            $hasRealFilters = !empty($filters['subject']) || !empty($filters['topic']) || !empty($filters['keyword']) || !empty($filters['type']) || !empty($filters['organization']) || !empty($filters['role']);
            $hasSuggestions = !empty($filters['suggestions']);

            if ($filters && ($hasRealFilters || $hasSuggestions)) {

                // === VALIDAÇÃO NO BANCO DE DADOS ===
                $query = \App\Models\Question::published();
                $query->where('tipo_questao', '!=', 'Redação');

                // Aplicar filtros da IA se presentes
                if (!empty($filters['type'])) {
                    $query->filterByType($filters['type']);
                }
                if (!empty($filters['subject'])) {
                    $query->filterBySubject($filters['subject']);
                }
                if (!empty($filters['topic'])) {
                    $query->filterByTopic($filters['topic']);
                }
                if (!empty($filters['difficulty'])) {
                    $query->where('difficulty', $filters['difficulty']);
                }
                if (!empty($filters['year'])) {
                    $query->where('year', $filters['year']);
                }
                if (!empty($filters['organization'])) {
                    $query->where('organization', 'like', '%' . $filters['organization'] . '%');
                }
                if (!empty($filters['institution'])) {
                    $query->where('institution', 'like', '%' . $filters['institution'] . '%');
                }
                if (!empty($filters['role'])) {
                    $query->where('role', 'like', '%' . $filters['role'] . '%');
                }
                if (!empty($filters['keyword'])) {
                    $query->where(function ($q) use ($filters) {
                        $q->where('statement', 'like', '%' . $filters['keyword'] . '%')
                            ->orWhere('explanation', 'like', '%' . $filters['keyword'] . '%');
                    });
                }

                $count = $query->count();

                // [Smart Recovery] Se a IA falhou em mapear o ID (ex: duplicidade no banco ou termo novo),
                // tentamos uma busca textual emergencial por Matéria antes de desistir.
                if ($count === 0 && empty($filters['suggestions'])) {
                    $cleanedPrompt = preg_replace('/[^A-Za-z0-9\s]/', '', $userPrompt);
                    $words = explode(' ', $cleanedPrompt);

                    // Procuramos por matérias que batam com palavras do prompt
                    $guessedSubject = \App\Models\Subject::where(function ($q) use ($words) {
                        foreach ($words as $word) {
                            if (strlen($word) > 3) {
                                $q->orWhere('name', 'like', "%{$word}%");
                            }
                        }
                    })->first();

                    if ($guessedSubject) {
                        $filters['subject'] = (string) $guessedSubject->id;
                        // Refazemos a query com o ID "adivinhado"
                        $query = \App\Models\Question::published()->where('tipo_questao', '!=', 'Redação');
                        if (!empty($filters['type']))
                            $query->filterByType($filters['type']);
                        $query->filterBySubject($filters['subject']);

                        $count = $query->count();
                        if ($count > 0) {
                            $filters['suggestion_tip'] = "O Xavier recalibrou a busca: Eu notei que você procura por '{$guessedSubject->name}' e encontrei estas questões para você.";
                        }
                    }
                }

                if ($count === 0 && empty($filters['suggestions'])) {
                    $filters['year'] = '';
                    $filters['difficulty'] = '';
                    $filters['organization'] = '';
                    $filters['institution'] = '';
                    $filters['role'] = '';
                    $filters['keyword'] = '';

                    $fallbackTopics = collect();
                    if (!empty($filters['subject'])) {
                        $fallbackTopics = \App\Models\Topic::whereHas('questions', function ($q) use ($filters) {
                            $q->published()->where('tipo_questao', '!=', 'Redação');
                            if (!empty($filters['type'])) {
                                $q->where('type', $filters['type']);
                            }
                            $q->whereHas('subjects', function ($s) use ($filters) {
                                $s->where('subjects.id', $filters['subject']);
                            });
                        })
                            ->withCount([
                                'questions' => function ($q) use ($filters) {
                                    $q->published()->where('tipo_questao', '!=', 'Redação');
                                    if (!empty($filters['type'])) {
                                        $q->where('type', $filters['type']);
                                    }
                                }
                            ])
                            ->orderByDesc('questions_count')
                            ->limit(3)
                            ->get();
                    }

                    $suggestions = [];
                    foreach ($fallbackTopics as $topic) {
                        $suggestions[] = [
                            'label' => $topic->name,
                            'filters' => [
                                'type' => $filters['type'] ?? '',
                                'subject' => $filters['subject'],
                                'topic' => $topic->id,
                            ]
                        ];
                    }

                    $filters['suggestion_tip'] = "Eu vasculhei todos os anos e bancas, mas não encontrei questões exatas para essa busca específica. Mas não se preocupe! Separei estes temas em alta que podem te interessar:";
                    $filters['suggestions'] = $suggestions;

                    $filters['topic'] = '';
                    if ($fallbackTopics->isEmpty()) {
                        $filters['subject'] = '';
                    }
                } else if ($count > 0 && $vector && empty($filters['suggestions'])) {
                    // Guarda o pensamento genial do Xavier APENAS se houverem resultados reais!
                    // Evita cachear alucinações vazias para próximos alunos.
                    $cacheService->storeInCache($userPrompt, $vector, $filters);
                }

                $this->searchRequest->update([
                    'filters' => $filters,
                    'status' => 'completed',
                ]);
            } else {
                $this->searchRequest->update([
                    'status' => 'failed',
                    'error' => 'Eu tentei cruzar todos os dados, mas acabei me perdendo entre tantos enunciados. Que tal tentarmos uma nova rota de busca?',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('InterpretSearchPromptJob failed: ' . $e->getMessage());
            $this->searchRequest->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
