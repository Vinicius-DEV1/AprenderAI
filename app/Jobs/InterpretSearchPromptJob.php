<?php

namespace App\Jobs;

use App\Models\AiSearchRequest;
use App\Services\AIService;
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

            if ($filters) {
                $this->searchRequest->update([
                    'filters' => $filters,
                    'status' => 'completed',
                ]);
            } else {
                $this->searchRequest->update([
                    'status' => 'failed',
                    'error' => 'Não conseguimos interpretar sua busca.',
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
