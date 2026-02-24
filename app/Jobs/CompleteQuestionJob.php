<?php

namespace App\Jobs;

use App\Models\Question;
use App\Services\AI\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CompleteQuestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $question;

    /**
     * Create a new job instance.
     */
    public function __construct(Question $question)
    {
        $this->question = $question;
    }

    /**
     * Execute the job.
     * Fills all missing fields (difficulty + explanation) in correct order.
     */
    public function handle(AIService $aiService): void
    {
        Log::info("Starting Complete Question Job for Question ID: {$this->question->id}");

        try {
            $result = $aiService->completeQuestion($this->question);

            Log::info("Complete Question Job finished for Question ID: {$this->question->id}", $result);
        }
        catch (\Exception $e) {
            Log::error("Failed to complete question {$this->question->id} in Job: " . $e->getMessage());
            throw $e;
        }
    }
}
