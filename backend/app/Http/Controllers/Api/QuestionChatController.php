<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuestionChatController extends Controller
{
    /**
     * Get chat history for a specific question.
     */
    public function chat(Request $request, Question $question)
    {
        $interactions = QuestionInteraction::where('user_id', $request->user()->id)
            ->where('question_id', $question->id)
            ->whereNull('simulation_id') // Standalone chat
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message', 'created_at']);

        return response()->json($interactions);
    }

    /**
     * Send a new chat message to Xavier.
     */
    public function sendChat(Request $request, Question $question)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();

        // 1. Check AI Quota
        if (!$user->hasAiQuota()) {
            return response()->json([
                'status' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de dúvidas do seu plano.',
                'upgrade_url' => '/plans'
            ]);
        }

        // 2. Save User Message
        QuestionInteraction::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        // 3. Increment usage immediately (prevent race conditions)
        $user->incrementAiUsage();

        // 4. Get history for context
        $history = QuestionInteraction::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->whereNull('simulation_id')
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message'])
            ->toArray();

        // 5. Stream response directly
        $lastAnswer = \App\Models\UserQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->orderByDesc('answered_at')
            ->value('selected_answer') ?? 'Não respondida';

        $aiService = app(\App\Services\AI\AIService::class);

        return response()->stream(function () use ($aiService, $question, $lastAnswer, $request, $history, $user) {
            // Disable buffering and compression for real-time streaming
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            while (ob_get_level()) {
                ob_end_flush();
            }

            try {
                $stream = $aiService->streamChatAboutStandaloneQuestion(
                    $question,
                    $lastAnswer,
                    $request->message,
                    $history,
                    $user->id
                );

                $fullResponse = '';
                $chunkBuffer = "";
                $chunkCounter = 0;
                foreach ($stream as $chunk) {
                    $fullResponse .= $chunk;
                    $chunkBuffer .= $chunk;
                    $chunkCounter++;

                    // Send first chunk immediately, then every 3-4 chunks to look "faster"
                    // Also flush on newlines to preserve formatting
                    if ($chunkCounter === 1 || $chunkCounter % 3 === 0 || str_contains($chunk, "\n")) {
                        // Correctly prefix every line with 'data: ' for SSE compatibility
                        $lines = explode("\n", $chunkBuffer);
                        foreach ($lines as $index => $line) {
                            echo "data: " . $line . "\n";
                        }
                        echo "\n"; // End of SSE event

                        $chunkBuffer = "";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                    }
                }

                // Final flush for remaining content
                if ($chunkBuffer !== "") {
                    echo "data: " . $chunkBuffer . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                }

                QuestionInteraction::create([
                    'simulation_id' => null,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $fullResponse ?: 'Desculpe, ocorreu um erro na IA.',
                ]);
            } catch (\Exception $e) {
                // If streaming crashes mid-way, just stop and log
                \Illuminate\Support\Facades\Log::error("Chat streaming aborted: " . $e->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none',
        ]);
    }
}
