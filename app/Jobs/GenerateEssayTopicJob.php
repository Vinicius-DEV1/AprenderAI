<?php

namespace App\Jobs;

use App\Models\Essay;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEssayTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $essayId;
    public $tries = 3;
    public $timeout = 90;

    /**
     * Create a new job instance.
     */
    public function __construct(int $essayId)
    {
        $this->essayId = $essayId;
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [3, 10, 20];
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        $essay = Essay::find($this->essayId);

        if (!$essay) {
            Log::error("GenerateEssayTopicJob: Essay not found ID {$this->essayId}");
            return;
        }

        try {
            // Idempotency check
            if (!empty($essay->topic_description)) {
                return;
            }

            // Limit check
            if ($essay->topic_regen_count >= 3) {
                Log::warning("GenerateEssayTopicJob: Limit reached for Essay {$essay->id}");
                $essay->update(['status' => 'error']); // Or keep as is, but UI needs to know
                return;
            }

            // Update status to indicate processing (if not already)
            // $essay->update(['status' => 'generating_topic']); 
            // User requested "ready_to_write" on success. 
            // "generating_topic" is not a standard status in the enum maybe? 
            // Existing statuses: pending, in_progress, evaluating, completed, error.
            // "in_progress" is fine for now, UI handles "Gerando schema...".

            $topic = $aiService->generateEssayTopic($essay->type);

            $essay->update([
                'title' => $topic['title'],
                'topic_description' => $topic['description'],
                'topic_regen_count' => $essay->topic_regen_count + 1,
                'status' => 'in_progress', // Ready to write
            ]);

            Log::info("Topic generated successfully for Essay {$essay->id}");

        } catch (\Throwable $e) {
            Log::error("GenerateEssayTopicJob Failed for Essay {$essay->id}: " . $e->getMessage());

            // Mark as error so UI can show retry button
            $essay->update(['status' => 'error']);

            throw $e; // Trigger retry
        }
    }
}
