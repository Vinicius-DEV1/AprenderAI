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
                'quota_max' => $user->plan->max_ai_questions,
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

    public function index(Request $request, Simulation $simulation, Question $question)
    {
        $history = QuestionInteraction::where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($history);
    }
}
