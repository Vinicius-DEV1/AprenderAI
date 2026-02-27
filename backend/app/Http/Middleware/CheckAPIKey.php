<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AI\AIService;

class CheckAPIKey
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->aiService->hasActiveKey()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Correção temporariamente indisponível',
                    'message' => 'Nenhuma chave de API configurada. Entre em contato com o suporte.'
                ], 503);
            }

            return redirect()->back()->with('error', 'Correção temporariamente indisponível. Insira uma chave de API válida nas configurações.');
        }

        return $next($request);
    }
}
