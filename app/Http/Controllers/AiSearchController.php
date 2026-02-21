<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\QuestionService;
use Illuminate\Http\Request;

class AiSearchController extends Controller
{
    protected AIService $aiService;
    protected QuestionService $questionService;

    public function __construct(AIService $aiService, QuestionService $questionService)
    {
        $this->aiService = $aiService;
        $this->questionService = $questionService;
    }

    /**
     * Endpoint para processar busca natural via IA.
     * Retorna os filtros sugeridos para serem aplicados no frontend.
     */
    public function search(Request $request)
    {
        $user = $request->user();

        // 1. Verificação de Plano (Básico e Plus apenas)
        // Bloqueia 'Gratuito' (Free)
        if (!$user->plan || $user->plan->name === 'Gratuito') {
            return response()->json([
                'status' => 'error',
                'message' => 'A busca assistida por IA está disponível apenas para alunos nos planos Básico e Plus.',
                'code' => 'plan_restricted'
            ], 403);
        }

        $request->validate([
            'prompt' => 'required|string|min:3|max:200'
        ]);

        // 2. Obter opções de filtro válidas para orientar a IA
        $filterOptions = $this->questionService->getFilterOptions();

        // 3. Interpretar via IA (Xavier)
        $filters = $this->aiService->interpretSearchPrompt($request->prompt, $filterOptions);

        if (!$filters) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não conseguimos interpretar sua busca agora. Tente novamente com palavras mais simples.'
            ], 500);
        }

        // Incrementa uso de IA do usuário
        $user->incrementAiUsage();

        return response()->json([
            'status' => 'success',
            'filters' => $filters,
            'suggestion_tip' => $filters['suggestion_tip'] ?? null,
            'suggestions' => $filters['suggestions'] ?? []
        ]);
    }
}
