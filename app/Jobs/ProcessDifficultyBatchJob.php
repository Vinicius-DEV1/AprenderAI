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

class ProcessDifficultyBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $limit;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 10)
    {
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        Log::info("Starting AI Difficulty Batch Job. Limit: {$this->limit}");

        $questions = Question::whereNull('difficulty_reasoning')
            ->limit($this->limit)
            ->get();

        $processed = 0;

        foreach ($questions as $question) {
            try {
                if ($aiService->evaluateQuestionDifficulty($question)) {
                    $processed++;
                }

                // Small delay to be polite to APIs and avoid rate limits
                usleep(500000); // 0.5 seconds

            }
            catch (\Exception $e) {
                Log::error("Failed to process question {$question->id} in Batch Job: " . $e->getMessage());
            }
        }

        Log::info("Finished AI Difficulty Batch Job. Processed: $processed");
    }
}
