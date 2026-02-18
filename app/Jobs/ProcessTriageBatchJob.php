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

class ProcessTriageBatchJob implements ShouldQueue
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
     * Processes incomplete questions, filling all missing fields.
     */
    public function handle(AIService $aiService): void
    {
        Log::info("Starting Triage Batch Job. Limit: {$this->limit}");

        $questions = Question::incomplete()
            ->limit($this->limit)
            ->get();

        $processed = 0;

        foreach ($questions as $question) {
            try {
                $result = $aiService->completeQuestion($question);

                if ($result['difficulty_filled'] || $result['explanation_filled']) {
                    $processed++;
                }

                // Delay to avoid API rate limits
                usleep(500000); // 0.5 seconds

            }
            catch (\Exception $e) {
                Log::error("Failed to complete question {$question->id} in Triage Batch Job: " . $e->getMessage());
            }
        }

        Log::info("Finished Triage Batch Job. Processed: $processed");
    }
}
