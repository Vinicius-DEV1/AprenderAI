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

        // 3. Call AI
        try {
            $responseMessage = $this->aiService->chatAboutQuestion($question, $simulation, $request->message, $history);
            
            // 4. Save AI Response
            if ($responseMessage) {
                QuestionInteraction::create([
                    'simulation_id' => $simulation->id,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $responseMessage,
                ]);

                return response()->json(['message' => $responseMessage]);
            } else {
                 return response()->json(['error' => 'Erro ao processar resposta da IA.'], 500);
            }

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '429')) {
                 Log::warning("Chat 429 Error - User: " . $user->id . " - Error: " . $e->getMessage());
                 return response()->json(['error' => 'Desculpe, estou processando muitas dúvidas agora. Tente novamente em 1 minuto.'], 429);
            }
            Log::error("Chat Error: " . $e->getMessage());
            return response()->json(['error' => 'Erro interno no chat.'], 500);
        }
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
