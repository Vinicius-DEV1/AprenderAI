<?php

namespace App\Jobs;

use App\Models\Essay;
use App\Services\AI\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GenerateEssayTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $essayId;
    public $tries = 2; // Reduced to 2 to avoid infinite polling delay of 3+20s
    public $timeout = 60;

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
        return [3, 10];
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
            if (!empty($essay->topic_description) && !str_starts_with($essay->topic_description, '__AI_ERROR__:')) {
                return;
            }

            // Limit check
            if ($essay->topic_regen_count >= 3) {
                Log::warning("GenerateEssayTopicJob: Limit reached for Essay {$essay->id}");
                $essay->update(['status' => 'error']); // Or keep as is, but UI needs to know
                return;
            }

            $topic = $aiService->generateEssayTopic($essay->type, $essay->user_id);

            $essay->update([
                'title' => $topic['title'],
                'topic_description' => $topic['description'],
                'topic_regen_count' => $essay->topic_regen_count + 1,
                'status' => 'in_progress', // Ready to write
            ]);

            // Clear any previous error cache
            Cache::forget("essay:topic:error:{$essay->id}");

            Log::info("Topic generated successfully for Essay {$essay->id}");

        } catch (\Throwable $e) {
            Log::error("GenerateEssayTopicJob Failed for Essay {$essay->id}: " . $e->getMessage());

            // Check if it's our standardized JSON error
            $decodedError = json_decode($e->getMessage(), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($decodedError['code']) && $decodedError['code'] === 'AI_TOPIC_GENERATION_FAILED') {
                // Store error gracefully in Cache
                Cache::put("essay:topic:error:{$essay->id}", $decodedError, now()->addMinutes(10));

                // Fallback if Cache fails for some reason (using topic_description as sentinela without breaking migrations)
                $essay->update([
                    'status' => 'error',
                    'topic_description' => '__AI_ERROR__:' . json_encode($decodedError)
                ]);

                // Do not rethrow standardized errors (we already tried with backoff inside AIService)
                return;
            }

            // Mark as error so UI can show retry button. For other types of errors, let Laravel retry once if tries < 2
            $essay->update(['status' => 'error']);

            throw $e;
        }
    }
}
