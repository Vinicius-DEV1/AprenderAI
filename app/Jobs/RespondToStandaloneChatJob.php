<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RespondToStandaloneChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(protected
        Question $question, protected
        string $userAnswer, protected
        string $message, protected
        array $history, protected
        int $userId
        )
    {
    }

    /**
     * Executes the AI response job.
     * Wrapped in a global try/catch for "indestructible" telemetry.
     */
    public function handle(AIService $aiService): void
    {
        try {
            // Identify active key for logging purposes in case of failure
            $provider = \App\Models\ApiKey::getActiveKeyForProvider('gemini') ? 'gemini' : 'openai';
            $apiKey = \App\Models\ApiKey::getActiveKeyForProvider($provider);

            $response = $aiService->chatAboutStandaloneQuestion(
                $this->question,
                $this->userAnswer,
                $this->message,
                $this->history
            );

            // Save AI response to the interaction table
            QuestionInteraction::create([
                'simulation_id' => null,
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'role' => 'assistant',
                'message' => $response ?? 'Desculpe, não consegui processar sua dúvida.',
            ]);

        }
        catch (\Throwable $e) {
            // 1. Log to Laravel Log for debugging
            Log::error("RespondToStandaloneChatJob Critical Failure", [
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 2. Persist to ApiLog (SRE Dashboard)
            // Note: AIService::callAI already logs API-level errors. 
            // This catch handles logic errors, formatting issues, or unexpected crashes.
            try {
                \App\Models\ApiLog::create([
                    'api_key_id' => isset($apiKey) ? $apiKey->id : null,
                    'provider' => $provider ?? 'system',
                    'type' => 'error',
                    'status_code' => 500,
                    'message' => "Job Failure: " . substr($e->getMessage(), 0, 200),
                    'payload' => ['trace' => substr($e->getTraceAsString(), 0, 1000)]
                ]);
            }
            catch (\Exception $logEx) {
                Log::error("Failed to persist SRE log: " . $logEx->getMessage());
            }

            // 3. User feedback (Save a failure message in the chat)
            QuestionInteraction::create([
                'simulation_id' => null,
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'role' => 'assistant',
                'message' => 'Desculpe, ocorreu um erro técnico ao processar sua dúvida. O administrador foi notificado.',
            ]);

            // Allow the job to fail and be retried by the queue if appropriate
            throw $e;
        }
    }
}
