<?php

namespace App\Http\Controllers;

use App\Models\AiSearchRequest;
use App\Jobs\InterpretSearchPromptJob;
use App\Services\QuestionService;
use Illuminate\Http\Request;

class AiSearchController extends Controller
{
    protected QuestionService $questionService;

    public function __construct(QuestionService $questionService)
    {
        $this->questionService = $questionService;
    }

    /**
     * Endpoint para processar busca natural via IA.
     * Inicia o job assíncrono e retorna o ID da requisição.
     */
    public function search(Request $request)
    {
        $user = $request->user();

        // 1. Verificação de Plano
        if (!$user->plan || $user->plan->name === 'Gratuito') {
            return response()->json([
                'status' => 'error',
                'message' => 'A busca assistida por IA está disponível apenas para alunos nos planos Básico e Plus.',
                'code' => 'plan_restricted'
            ], 403);
        }

        // 2. Verificação de Cota
        if (!$user->hasAiQuota()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Você atingiu o limite de uso de IA do seu plano.',
                'code' => 'quota_exceeded'
            ], 403);
        }

        $request->validate([
            'prompt' => 'required|string|min:3|max:200'
        ]);

        // 3. Criar registro de busca
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt' => $request->prompt,
            'status' => 'pending'
        ]);

        // 4. Disparar Job
        InterpretSearchPromptJob::dispatch($searchRequest);

        // Incrementa uso de IA do usuário
        $user->incrementAiUsage();

        return response()->json([
            'status' => 'queued',
            'request_id' => $searchRequest->id
        ]);
    }

    /**
     * Endpoint de polling para verificar o status da interpretação.
     */
    public function status(AiSearchRequest $searchRequest)
    {
        // Garante que o usuário só acessa suas próprias requisições
        if ($searchRequest->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'Não autorizado'], 403);
        }

        $friendlyError = null;
        if ($searchRequest->status === 'failed') {
            $error = $searchRequest->error;
            $friendlyError = 'Eu me perdi entre tantos enunciados enquanto tentava cruzar seus dados.';

            if (str_contains($error, '429') || str_contains($error, 'Quota')) {
                $friendlyError = 'Estou recebendo muitas requisições agora. Poderia aguardar um instante na minha mesa de espera para eu processar sua busca?';
            } elseif (str_contains($error, '401') || str_contains($error, '403') || str_contains($error, 'Key')) {
                $friendlyError = 'Minha mesa de análise está passando por uma manutenção rápida. Voltarei com os resultados em breve!';
            } elseif (str_contains($error, 'timeout') || str_contains($error, 'slow')) {
                $friendlyError = 'A busca na imensidão de dados demorou mais que o esperado. Vamos tentar uma rota mais específica?';
            }
        }

        return response()->json([
            'status' => $searchRequest->status,
            'filters' => $searchRequest->filters,
            'error' => $friendlyError,
            'suggestion_tip' => $searchRequest->filters['suggestion_tip'] ?? null,
            'suggestions' => $searchRequest->filters['suggestions'] ?? []
        ]);
    }
}
