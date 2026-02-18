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

    public function handle(AIService $aiService): void
    {
        try {
            $response = $aiService->chatAboutStandaloneQuestion(
                $this->question,
                $this->userAnswer,
                $this->message,
                $this->history
            );

            // Save AI response
            QuestionInteraction::create([
                'simulation_id' => null,
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'role' => 'assistant',
                'message' => $response ?? 'Desculpe, não consegui processar sua dúvida.',
            ]);

        }
        catch (\Exception $e) {
            Log::error("RespondToStandaloneChatJob failed", [
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            QuestionInteraction::create([
                'simulation_id' => null,
                'question_id' => $this->question->id,
                'user_id' => $this->userId,
                'role' => 'assistant',
                'message' => 'Desculpe, ocorreu um erro ao processar sua dúvida. Tente novamente.',
            ]);
        }
    }
}
