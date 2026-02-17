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
        $keys = ApiKey::orderBy('provider')->get();

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

        return view('admin.api-keys', compact('keys', 'logs', 'aiLogs', 'aiRanking', 'hasRecentErrors'));
    }

    public function storeApiKey(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:openai,gemini,grok',
            'key' => 'required|string',
            'preferred_model' => 'nullable|string',
        ]);

        // Se for a primeira chave deste provider, torna-a primária
        $isPrimary = !ApiKey::where('provider', $request->provider)->where('is_primary', true)->exists();

        ApiKey::create([
            'provider' => $request->provider,
            'key' => $request->key, // Setter encrypts automatically
            'preferred_model' => $request->preferred_model,
            'is_valid' => true, // Assumimos válido se o user salvou após teste (ou podemos forçar teste)
            'is_active' => true,
            'is_primary' => $isPrimary,
            'status' => 'online', // Fix: Garantir que a chave nasça online para ser pega pelo AIService
        ]);

        return back()->with('success', 'Chave adicionada com sucesso!');
    }

    public function toggleApiKey(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return back()->with('success', 'Status da chave atualizado!');
    }

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
        $apiKey->delete();

        // Se deletou a primária, promove outra
        if ($apiKey->is_primary) {
            $nextKey = ApiKey::where('provider', $apiKey->provider)->first();
            if ($nextKey) {
                $nextKey->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'Chave removida!');
    }

    public function retestApiKey(ApiKey $apiKey)
    {
        $aiService = app(\App\Services\AIService::class);

        try {
            $result = $aiService->validateKey($apiKey->provider, $apiKey->decrypted_key);

            if ($result['is_valid']) {
                $apiKey->update([
                    'status' => 'online',
                    'last_health_check_at' => now(),
                ]);
                return back()->with('success', "Provedor {$apiKey->provider} validado com sucesso! (Online)");
            }
            else {
                $status = str_contains($result['error'] ?? '', '429') ? 'quota_exceeded' : 'offline';
                $apiKey->update([
                    'status' => $status,
                    'last_health_check_at' => now(),
                ]);
                return back()->with('error', "Provedor {$apiKey->provider} falhou: " . ($result['error'] ?? 'Erro desconhecido'));
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
