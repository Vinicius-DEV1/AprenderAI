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

class EvaluateEssayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $essay;

    public $tries = 5;
    public $timeout = 120;

    public function __construct(Essay $essay)
    {
        $this->essay = $essay;
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(AIService $aiService): void
    {
        Log::info("Starting evaluation for Essay ID: {$this->essay->id}");

        // Reload to get fresh status
        $this->essay->refresh();

        if ($this->essay->status === 'completed') {
            Log::info("Essay ID: {$this->essay->id} already completed. Skipping.");
            return;
        }

        try {
            $this->essay->update(['status' => 'evaluating']);

            // --- OFF-TOPIC GATE ---
            $evalContent = $this->essay->input_type === 'image' && $this->essay->extracted_text
                ? $this->essay->extracted_text
                : $this->essay->content;

            $offTopicResult = $aiService->detectOffTopic(
                $this->essay->title,
                $evalContent,
                $this->essay->type,
                $this->essay->user_id
            );

            if ($offTopicResult['off_topic'] === true) {
                $this->essay->update([
                    'status' => 'completed',
                    'score' => 0,
                    'competencies' => [], // Ensure API/Resource uses 0s
                    'off_topic' => true,
                    'off_topic_reason' => $offTopicResult['reason'] ?? 'Você fugiu do tema proposto.',
                    'final_score_locked' => true,
                    'evaluated_at' => now(),
                    'feedback_json' => [
                        'score' => 0,
                        'summary' => 'Fuga do tema: nota 0.',
                        'strengths' => [],
                        'weaknesses' => ['Fuga do tema propoto.'],
                        'corrections' => [],
                        'improved_version' => '',
                        'competencies' => []
                    ],
                ]);

                Log::info("Essay ID: {$this->essay->id} detected as OFF-TOPIC. Score locked to 0.");
                return; // LOCK - Stop processing
            }
            // --- END OFF-TOPIC GATE ---

            $result = $aiService->evaluateEssay(
                $this->essay->title,
                $evalContent,
                $this->essay->type,
                $this->essay->user_id
            );

            if ($result && isset($result['response'])) {
                $response = $result['response'];

                $this->essay->update([
                    'status' => 'completed',
                    'score' => $response['score'] ?? 0,
                    'feedback_json' => $response,
                    'evaluated_at' => now(),
                    'ai_suggestions' => $response['corrections'] ?? [],
                ]);

                Log::info("Essay ID: {$this->essay->id} evaluated successfully. Score: {$this->essay->score}");
            } else {
                Log::error("Essay ID: {$this->essay->id} evaluation returned empty result.");
                throw new \Exception("Empty result from AI Service (Provider: " . ($result['provider'] ?? 'unknown') . ")");
            }

        } catch (\Throwable $e) {
            Log::error("Essay ID: {$this->essay->id} evaluation failed: " . $e->getMessage());
            // Rethrow to trigger Laravel retry mechanism
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Essay ID: {$this->essay->id} FAILED after all retries. Exception: " . $exception->getMessage());

        $this->essay->update([
            'status' => 'error',
            // Optional: Save error message if you have a column for it, 
            // but requirement says "without forbidden terms". 
            // 'feedback' => 'Não foi possível corrigir agora. Tente novamente.' 
        ]);
    }
}
