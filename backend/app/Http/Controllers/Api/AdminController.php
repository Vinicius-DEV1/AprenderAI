<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get admin dashboard KPIs and chart data.
     */
    public function dashboard()
    {
        // 1. KPIs
        $activeSubscriptions = Subscription::where('status', 'active')->paid()->count();

        $revenue = Subscription::where('status', 'active')
            ->paid()
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->sum('plans.price');

        $newUsersThisWeek = User::where('created_at', '>=', now()->startOfWeek())->count();
        $newUsersLastWeek = User::whereBetween('created_at', [
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
                Subscription::where('created_at', '<=', $date->endOfMonth())
                    ->paid()
                    ->where(function ($query) use ($date) {
                        $query->whereNull('canceled_at')
                            ->orWhere('canceled_at', '>', $date->endOfMonth());
                    })
                    ->count()
            );
        }

        $userStats = [
            'active' => User::whereHas('subscriptions', fn($q) => $q->where('status', 'active')->paid())->count(),
            'inactive' => User::doesntHave('subscriptions', 'and', fn($q) => $q->where('status', 'active')->paid())->count(),
        ];

        // 3. Activity Feed
        $latestUsers = User::latest()->take(5)->get()->map(function ($user) {
            return [
                'type' => 'user',
                'message' => "Novo usuário cadastrado: {$user->name}",
                'created_at' => $user->created_at->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($user->name)
                ]
            ];
        });

        $latestSubs = Subscription::with(['user', 'plan'])->latest()->take(5)->get()->map(function ($sub) {
            $typeString = $sub->is_manual_grant ? 'ganhou o plano' : 'assinou o plano';
            if ($sub->is_sandbox)
                $typeString .= ' (Sandbox)';
            return [
                'type' => 'subscription',
                'message' => ($sub->user->name ?? 'Usuário') . " {$typeString} " . ($sub->plan->name ?? 'Grátis'),
                'created_at' => $sub->created_at->toIso8601String(),
                'user' => [
                    'id' => $sub->user->id ?? 0,
                    'name' => $sub->user->name ?? 'Desconhecido',
                    'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($sub->user->name ?? 'U')
                ]
            ];
        });

        $activityFeed = $latestUsers->concat($latestSubs)->sortByDesc('created_at')->take(10)->values();

        return response()->json([
            'kpis' => [
                'active_subscriptions' => $activeSubscriptions,
                'revenue' => round($revenue, 2),
                'new_users_this_week' => $newUsersThisWeek,
                'user_growth_direction' => $userGrowthDirection,
            ],
            'charts' => [
                'labels' => $months,
                'subscriptions' => $subscriptionsGrowth,
                'user_distribution' => [
                    $userStats['active'],
                    $userStats['inactive']
                ]
            ],
            'activity_feed' => $activityFeed
        ]);
    }
}
