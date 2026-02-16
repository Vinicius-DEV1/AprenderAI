<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\Simulation;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RespondToChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $simulation;
    protected $question;
    protected $userMessage;
    protected $history;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(Simulation $simulation, Question $question, string $userMessage, array $history, int $userId)
    {
        $this->simulation = $simulation;
        $this->question = $question;
        $this->userMessage = $userMessage;
        $this->history = $history;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(AIService $aiService): void
    {
        try {
            // Call AI Service
            $responseMessage = $aiService->chatAboutQuestion(
                $this->question,
                $this->simulation,
                $this->userMessage,
                $this->history
            );

            if ($responseMessage) {
                // Save Assistant Response
                QuestionInteraction::create([
                    'simulation_id' => $this->simulation->id,
                    'question_id' => $this->question->id,
                    'user_id' => $this->userId,
                    'role' => 'assistant',
                    'message' => $responseMessage,
                ]);
            }

        }
        catch (\Exception $e) {
            Log::error("RespondToChatJob Error: " . $e->getMessage());

        // Optional: Save an error message to the chat so the user knows it failed?
        // For now, logging is enough. Frontend will timeout polling.
        }
    }
}
