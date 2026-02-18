<?php

namespace App\Jobs;

use App\Models\Question;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateQuestionDifficultyJob implements ShouldQueue
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
     */
    public function handle(AIService $aiService): void
    {
        Log::info("Starting Individual AI Difficulty Evaluation Job for Question ID: {$this->question->id}");

        try {
            $result = $aiService->evaluateQuestionDifficulty($this->question);

            if ($result) {
                Log::info("Successfully evaluated Question ID: {$this->question->id} via Job.");
            }
            else {
                Log::warning("AI Service returned no result for Question ID: {$this->question->id} in Job.");
            }
        }
        catch (\Exception $e) {
            Log::error("Failed to evaluate question {$this->question->id} in Job: " . $e->getMessage());
            throw $e; // Rethrow to allow queue retry if configured
        }
    }
}
