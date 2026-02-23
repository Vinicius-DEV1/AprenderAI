<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        // 1. KPIs
        $activeSubscriptions = \App\Models\Subscription::where('status', 'active')->count();

        $revenue = \App\Models\Subscription::where('status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        $newUsersThisWeek = \App\Models\User::where('created_at', '>=', now()->startOfWeek())->count();
        $newUsersLastWeek = \App\Models\User::whereBetween('created_at', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ])->count();

        $userGrowthDirection = $newUsersThisWeek >= $newUsersLastWeek ? 'up' : 'down';

        // 2. Charts Data (6 months)
        $months = collect([]);
        $subscriptionsGrowth = collect([]);

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date->format('M/Y'));
            $subscriptionsGrowth->push(
                \App\Models\Subscription::where('created_at', '<=', $date->endOfMonth())
                ->where(function ($query) use ($date) {
                $query->whereNull('canceled_at')
                    ->orWhere('canceled_at', '>', $date->endOfMonth());
            })
                ->count()
            );
        }

        $userStats = [
            'active' => \App\Models\User::whereHas('subscriptions', fn($q) => $q->where('status', 'active'))->count(),
            'inactive' => \App\Models\User::doesntHave('subscriptions')->count(),
        ];

        // 3. Activity Feed
        $latestUsers = \App\Models\User::latest()->take(5)->get()->map(function ($user) {
            return [
            'type' => 'user',
            'message' => "Novo usuário cadastrado: {$user->name}",
            'created_at' => $user->created_at,
            'user' => $user
            ];
        });

        $latestSubs = \App\Models\Subscription::with(['user', 'plan'])->latest()->take(5)->get()->map(function ($sub) {
            return [
            'type' => 'subscription',
            'message' => "{$sub->user->name} assinou o plano {$sub->plan->name}",
            'created_at' => $sub->created_at,
            'user' => $sub->user
            ];
        });

        $activityFeed = $latestUsers->concat($latestSubs)->sortByDesc('created_at')->take(10);

        return view('admin.dashboard', compact(
            'activeSubscriptions',
            'revenue',
            'newUsersThisWeek',
            'userGrowthDirection',
            'months',
            'subscriptionsGrowth',
            'userStats',
            'activityFeed'
        ));
    }

    public function apiKeys()
    {
        $vaultKeys = \App\Models\ApiKeyVault::orderBy('nickname')->get();
        
        // NOVO: Nós não iteramos mais por "Routing Keys", mas sim por "Capacidades" e suas pilhas de modelos
        $capabilitiesGrid = [];
        $availableCapabilities = ApiKey::getAvailableCapabilities();

        foreach($availableCapabilities as $cap => $label) {
            $capabilitiesGrid[$cap] = ApiKey::getKeysForCapability($cap);
        }

        // SRE: Fetch last 20 health check logs
        $logs = \App\Models\ApiLog::with('apiKey')->latest()->take(20)->get();

        // NEW: Fetch last 20 AI transaction logs
        $aiLogs = \App\Models\AiRequestLog::with('user')->latest()->take(20)->get();

        // NEW: Top 20 AI Consumers Ranking (Cached for 1 hour)
        $aiRanking = \Illuminate\Support\Facades\Cache::remember('ai_consumption_ranking', 3600, function () {
            return \App\Models\AiRequestLog::query()
            ->selectRaw('user_id, SUM(tokens_used_total) as total_tokens, SUM(estimated_cost) as total_cost, COUNT(*) as request_count')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('total_tokens')
            ->with('user')
            ->limit(20)
            ->get();
        });

        // SRE: Check for errors in last 6 hours
        $hasRecentErrors = \App\Models\ApiLog::where('type', 'error')
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        return view('admin.api-keys', compact('vaultKeys', 'capabilitiesGrid', 'logs', 'aiLogs', 'aiRanking', 'hasRecentErrors', 'availableCapabilities'));
    }

    public function storeVaultKey(Request $request)
    {
        $request->validate([
            'nickname' => 'required|string|unique:api_key_vaults,nickname',
            'provider' => 'required|in:openai,gemini,grok',
            'key' => 'required|string',
        ]);

        \App\Models\ApiKeyVault::create($request->only(['nickname', 'provider', 'key']) + ['is_valid' => true]);

        return back()->with('success', 'Chave adicionada ao Cofre com sucesso!');
    }

    public function discoverModels(Request $request)
    {
        $request->validate(['vault_id' => 'required|exists:api_key_vaults,id']);
        
        try {
            $vault = \App\Models\ApiKeyVault::findOrFail($request->vault_id);
            $aiService = app(\App\Services\AIService::class);
            $result = $aiService->validateKey($vault->provider, $vault->decrypted_key);

            if (!$result['is_valid']) {
                return response()->json($result, 422);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'is_valid' => false,
                'error' => 'Falha interna: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeApiKey(Request $request)
    {
        $request->validate([
            'vault_id' => 'required|exists:api_key_vaults,id',
            'preferred_model' => 'required|string',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string'
        ]);

        $vault = \App\Models\ApiKeyVault::findOrFail($request->vault_id);
        
        // Se o usuário não selecionou nada, usamos o General como fallback de segurança
        $requestedCapabilities = $request->capabilities;
        if (empty($requestedCapabilities)) {
            $requestedCapabilities = [ApiKey::CAPABILITY_GENERAL];
        }

        // Se for a primeira chave deste provider, torna-a primária (Legacy)
        $isPrimary = !ApiKey::where('provider', $vault->provider)->where('is_primary', true)->exists();

        $apiKey = ApiKey::create([
            'vault_id' => $vault->id,
            'provider' => $vault->provider, 
            'preferred_model' => $request->preferred_model,
            'is_valid' => true,
            'is_active' => true,
            'is_primary' => $isPrimary,
            'status' => 'online',
        ]);

        // CADASTRAR APENAS AS CAPACIDADES SELECIONADAS NO FORMULÁRIO
        foreach($requestedCapabilities as $cap) {
            // Prioridade: Puxar sempre para o fim da fila daquela categoria específica
            $currentMaxPriority = \App\Models\ApiKeyCapability::where('capability', $cap)->max('priority') ?? 0;
            
            \App\Models\ApiKeyCapability::firstOrCreate([
                'api_key_id' => $apiKey->id,
                'capability' => $cap
            ], [
                'priority' => $currentMaxPriority + 1
            ]);
        }

        return back()->with('success', 'Roteamento configurado com sucesso! O modelo foi vinculado apenas às funcionalidades selecionadas.');
    }

    public function updateCapabilitiesPriority(Request $request)
    {
        $request->validate([
            'capability' => 'required|string',
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'exists:api_key_capabilities,id'
        ]);

        foreach ($request->ordered_ids as $index => $id) {
            \App\Models\ApiKeyCapability::where('id', $id)
                ->where('capability', $request->capability)
                ->update(['priority' => $index + 1]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleApiKey(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return back()->with('success', 'Status da provedor de IA atualizado globalmente!');
    }

    /**
     * Proxies the connection test to AIService.
     * Returms list of models on success, enabling dynamic selection in the UI.
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'key' => 'required|string',
        ]);

        $aiService = app(\App\Services\AIService::class);
        $result = $aiService->validateKey($request->provider, $request->key);

        return response()->json($result);
    }

    public function destroyApiKey(ApiKey $apiKey)
    {
        // Ao deletar uma API Key com cascade on delete, todas as capabilities atreladas na pivô são excluídas automaticamente
        $apiKey->delete();

        // Se deletou a primária, promove outra (Legacy mode)
        if ($apiKey->is_primary) {
            $nextKey = ApiKey::where('provider', $apiKey->provider)->first();
            if ($nextKey) {
                $nextKey->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'Chave e todos os seus vínculos de roteamento foram removidos!');
    }

    /**
     * Executes an asynchronous health check on an existing key.
     * Updates status to 'online', 'offline', or 'quota_exceeded' based on model listing.
     */
    public function retestApiKey(ApiKey $apiKey)
    {
        $aiService = app(\App\Services\AIService::class);

        try {
            $result = $aiService->validateKey($apiKey->effective_provider, $apiKey->decrypted_key);

            if ($result['is_valid']) {
                $apiKey->update([
                    'status' => 'online',
                    'last_health_check_at' => now(),
                ]);
                return back()->with('success', "Roteamento em {$apiKey->effective_provider} validado com sucesso! (Online)");
            }
            else {
                $status = str_contains($result['error'] ?? '', '429') ? 'quota_exceeded' : 'offline';
                $apiKey->update([
                    'status' => $status,
                    'last_health_check_at' => now(),
                ]);
                return back()->with('error', "Roteamento em {$apiKey->effective_provider} falhou: " . ($result['error'] ?? 'Erro desconhecido'));
            }
        }
        catch (\Exception $e) {
            $apiKey->update([
                'status' => 'offline',
                'last_health_check_at' => now(),
            ]);
            return back()->with('error', "Exceção ao testar {$apiKey->provider}: " . $e->getMessage());
        }
    }

    public function clearLogs()
    {
        \App\Models\ApiLog::truncate();
        return back()->with('success', 'Histórico de logs limpo!');
    }
}
