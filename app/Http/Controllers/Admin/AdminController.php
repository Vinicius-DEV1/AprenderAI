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
                    ->where(function($query) use ($date) {
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
        $latestUsers = \App\Models\User::latest()->take(5)->get()->map(function($user) {
            return [
                'type' => 'user',
                'message' => "Novo usuário cadastrado: {$user->name}",
                'created_at' => $user->created_at,
                'user' => $user
            ];
        });

        $latestSubs = \App\Models\Subscription::with(['user', 'plan'])->latest()->take(5)->get()->map(function($sub) {
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
        return view('admin.api-keys', compact('keys'));
    }

    public function storeApiKey(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:openai,gemini,grok',
            'key' => 'required|string',
        ]);

        // Se for a primeira chave deste provider, torna-a primária
        $isPrimary = !ApiKey::where('provider', $request->provider)->where('is_primary', true)->exists();

        ApiKey::create([
            'provider' => $request->provider,
            'key' => $request->key, // Setter encrypts automatically
            'is_active' => true,
            'is_primary' => $isPrimary,
        ]);

        return back()->with('success', 'Chave adicionada com sucesso!');
    }

    public function toggleApiKey(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return back()->with('success', 'Status da chave atualizado!');
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
}
