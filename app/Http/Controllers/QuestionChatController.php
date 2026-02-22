<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\Simulation;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuestionChatController extends Controller
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function store(Request $request, Simulation $simulation, Question $question)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Verify if question belongs to simulation
        if (!$simulation->answers()->where('question_id', $question->id)->exists()) {
            return response()->json(['error' => 'Questão não pertence a este simulado.'], 403);
        }

        $user = $request->user();
        Log::info("Chat Request - User: " . $user->id . " - Question: " . $question->id . " - Message: " . $request->message);

        // Check AI Quota
        if (!$user->hasAiQuota()) {
            $resetDate = $user->last_reset_at
                ? $user->last_reset_at->addMonth()->format('d/m/Y')
                : now()->addMonth()->format('d/m/Y');

            return response()->json([
                'status' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de dúvidas do seu plano.',
                'quota_max' => $user->aiQuotaLimit(),
                'quota_used' => $user->ai_questions_count,
                'reset_date' => $resetDate,
                'upgrade_url' => route('dashboard') // Change to plans/upgrade route when available
            ]);
        }

        // Increment Usage immediately to prevent race conditions
        $user->incrementAiUsage();

        // 1. Save User Message
        QuestionInteraction::create([
            'simulation_id' => $simulation->id,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        // 2. Load History
        $history = QuestionInteraction::where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($interaction) => [
        'role' => $interaction->role,
        'message' => $interaction->message
        ])
            ->toArray();

        // 3. Dispatch Job (Async)
        // We pass the history *before* the current message because the Job might re-append or the AI Service might need context.
        // Actually, the Service probably expects the full history including the new user message? 
        // Let's check AIService signature: chatAboutQuestion($question, $simulation, $message, $history)
        // Usually history contains previous messages. The current message is passed as argument.
        // So passing $history as we fetched it (previous interactions) is correct.

        \App\Jobs\RespondToChatJob::dispatch(
            $simulation,
            $question,
            $request->message,
            $history,
            $user->id
        );

        // Return success immediately
        return response()->json(['status' => 'queued']);
    }

    /**
     * Handle streaming chat for simulation context.
     */
    public function stream(Request $request, Simulation $simulation, Question $question)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        if (!$simulation->answers()->where('question_id', $question->id)->exists()) {
            return response()->json(['error' => 'Questão não pertence a este simulado.'], 403);
        }

        $user = $request->user();

        if (!$user->hasAiQuota()) {
            return response()->json(['status' => 'quota_exceeded', 'message' => 'Você atingiu o limite de dúvidas.']);
        }

        $user->incrementAiUsage();

        QuestionInteraction::create([
            'simulation_id' => $simulation->id,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        $history = QuestionInteraction::where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($i) => ['role' => $i->role, 'message' => $i->message])
            ->toArray();

        return response()->stream(function () use ($question, $simulation, $request, $history, $user) {
            // Aggressive flushing
            if (function_exists('ob_implicit_flush')) ob_implicit_flush(true);
            while (ob_get_level() > 0) ob_end_flush();

            $fullText = "";
            try {
                $stream = $this->aiService->streamChatAboutQuestion($question, $simulation, $request->message, $history);
                
                foreach ($stream as $chunk) {
                    $fullText .= $chunk;
                    // SSE format
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            } catch (\Exception $e) {
                Log::error("Streaming error in simulation chat: " . $e->getMessage());
                
                $errorCode = $e->getCode();
                if (str_contains($e->getMessage(), '429')) {
                    echo "data: " . json_encode(['status' => 'provider_error', 'message' => 'O Xavier está recebendo muitas requisições agora. Tente novamente em alguns segundos.']) . "\n\n";
                } else {
                    echo "data: " . json_encode(['error' => 'Erro no processamento do Xavier: ' . $e->getMessage()]) . "\n\n";
                }
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            if ($fullText) {
                QuestionInteraction::create([
                    'simulation_id' => $simulation->id,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $fullText,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // For Nginx
        ]);
    }

    public function index(Request $request, Simulation $simulation, Question $question)
    {
        $history = QuestionInteraction::where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($history);
    }

    /**
     * Envia mensagem de chat avulso (sem simulado).
     */
    public function storeStandalone(Request $request, Question $question)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();
        Log::info("Standalone Chat Request - User: {$user->id} - Question: {$question->id} - Message: {$request->message}");

        // Check AI Quota
        if (!$user->hasAiQuota()) {
            $resetDate = $user->last_reset_at
                ? $user->last_reset_at->addMonth()->format('d/m/Y')
                : now()->addMonth()->format('d/m/Y');

            return response()->json([
                'status' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de dúvidas do seu plano.',
                'quota_max' => $user->plan->max_ai_questions,
                'quota_used' => $user->ai_questions_count,
                'reset_date' => $resetDate,
                'upgrade_url' => route('dashboard'),
            ]);
        }

        $user->incrementAiUsage();

        // Save User Message (simulation_id = null for standalone)
        QuestionInteraction::create([
            'simulation_id' => null,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        // Load History (standalone: simulation_id IS NULL)
        $history = QuestionInteraction::whereNull('simulation_id')
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($i) => ['role' => $i->role, 'message' => $i->message])
            ->toArray();

        // Get user's answer from user_question_answers
        $userAnswer = \App\Models\UserQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();

        $userAnswerText = $userAnswer ? $userAnswer->selected_answer : 'Não respondida';

        // Dispatch Job
        \App\Jobs\RespondToStandaloneChatJob::dispatch(
            $question,
            $userAnswerText,
            $request->message,
            $history,
            $user->id
        );

        return response()->json(['status' => 'queued']);
    }

    /**
     * Handle streaming chat for standalone context.
     */
    public function streamStandalone(Request $request, Question $question)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $user = $request->user();

        if (!$user->hasAiQuota()) {
            return response()->json(['status' => 'quota_exceeded', 'message' => 'Você atingiu o limite de dúvidas.']);
        }

        $user->incrementAiUsage();

        QuestionInteraction::create([
            'simulation_id' => null,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        $history = QuestionInteraction::whereNull('simulation_id')
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($i) => ['role' => $i->role, 'message' => $i->message])
            ->toArray();

        $userAnswer = \App\Models\UserQuestionAnswer::where('user_id', $user->id)
            ->where('question_id', $question->id)
            ->first();

        $userAnswerText = $userAnswer ? $userAnswer->selected_answer : 'Não respondida';

        return response()->stream(function () use ($question, $userAnswerText, $request, $history, $user) {
            // Aggressive flushing
            if (function_exists('ob_implicit_flush')) ob_implicit_flush(true);
            while (ob_get_level() > 0) ob_end_flush();

            $fullText = "";
            try {
                $stream = $this->aiService->streamChatAboutStandaloneQuestion($question, $userAnswerText, $request->message, $history);
                
                foreach ($stream as $chunk) {
                    $fullText .= $chunk;
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            } catch (\Exception $e) {
                Log::error("Streaming error in standalone chat: " . $e->getMessage());
                
                if (str_contains($e->getMessage(), '429')) {
                    echo "data: " . json_encode(['status' => 'provider_error', 'message' => 'O Xavier está recebendo muitas requisições agora. Tente novamente em alguns segundos.']) . "\n\n";
                } else {
                    echo "data: " . json_encode(['error' => 'Erro no processamento do Xavier: ' . $e->getMessage()]) . "\n\n";
                }
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            if ($fullText) {
                QuestionInteraction::create([
                    'simulation_id' => null,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $fullText,
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Histórico de chat avulso (sem simulado).
     */
    public function indexStandalone(Request $request, Question $question)
    {
        $user = $request->user();

        $history = QuestionInteraction::whereNull('simulation_id')
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($history);
    }
}
