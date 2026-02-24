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
    public function handle(AIService $aiService, QuestionService $questionService): void
    {
        try {
            $filterOptions = $questionService->getFilterOptions();
            $filters = $aiService->interpretSearchPrompt($this->searchRequest->prompt, $filterOptions);

            // Validação mínima: Consideramos sucesso se houver filtros reais OU sugestões de caminhos alternativos
            $hasRealFilters = !empty($filters['subject']) || !empty($filters['topic']) || !empty($filters['keyword']) || !empty($filters['type']);
            $hasSuggestions = !empty($filters['suggestions']);

            if ($filters && ($hasRealFilters || $hasSuggestions)) {
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
