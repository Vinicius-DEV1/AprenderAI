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

        // 2. Charts Data: count only CONFIRMED (active) subscriptions per month
        // Pending PIX/card charges must not inflate the "Novas Assinaturas" chart
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date->format('M/Y'));
            $subscriptionsGrowth->push(
                Subscription::where('status', 'active')          // only confirmed
                    ->where('created_at', '<=', $date->endOfMonth())
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

        // 3. Activity Feed — correctly label each entry based on subscription status
        // so admins can distinguish confirmed payments from pending intentions
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

        $latestSubs = Subscription::with(['user', 'plan'])->latest()->take(10)->get()->map(function ($sub) {
            // Determine the correct human-readable action based on status + billing type
            if ($sub->is_manual_grant) {
                $action  = 'ganhou o plano';
                $intent  = 'granted';           // purple badge
            } elseif ($sub->status === 'active') {
                $action  = 'assinou o plano';   // payment confirmed
                $intent  = 'confirmed';         // green badge
            } elseif ($sub->status === 'pending' && $sub->billing_type === 'pix') {
                $action  = 'gerou PIX para o plano';  // awaiting PIX scan
                $intent  = 'pix_pending';       // orange badge
            } elseif ($sub->status === 'pending') {
                $action  = 'iniciou contratação do plano';  // card/other pending
                $intent  = 'payment_pending';   // yellow badge
            } else {
                $action  = 'interagiu com o plano';
                $intent  = 'unknown';
            }

            return [
                'type'            => 'subscription',
                'message'         => ($sub->user->name ?? 'Usuário') . " {$action} " . ($sub->plan->name ?? 'Grátis'),
                'created_at'      => $sub->created_at->toIso8601String(),
                'is_sandbox'      => (bool) $sub->is_sandbox,
                'is_manual_grant' => (bool) $sub->is_manual_grant,
                'payment_intent'  => $intent,   // used by frontend to pick badge color
                'user' => [
                    'id'         => $sub->user->id ?? 0,
                    'name'       => $sub->user->name ?? 'Desconhecido',
                    'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($sub->user->name ?? 'U')
                ]
            ];
        });

        $activityFeed = $latestUsers->concat($latestSubs)->sortByDesc('created_at')->take(10)->values();

        // 4. Device and OS Stats (Last 30 days)
        $detector = new \App\Services\DeviceDetectorService();
        $recentSessions = \App\Models\PlatformSession::where('started_at', '>=', now()->subDays(30))
            ->select('user_agent')
            ->get();

        $deviceCounts = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0];
        $osCounts = [];

        foreach ($recentSessions as $session) {
            $ua = $session->user_agent;
            $category = $detector->getDeviceCategory($ua);
            $os = $detector->getOperatingSystem($ua);

            $deviceCounts[$category]++;
            $osCounts[$os] = ($osCounts[$os] ?? 0) + 1;
        }

        // Sort OS by count descending
        arsort($osCounts);
        $topOs = array_slice($osCounts, 0, 5, true);

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
                ],
                'device_distribution' => [
                    $deviceCounts['Desktop'],
                    $deviceCounts['Mobile'],
                    $deviceCounts['Tablet'],
                ],
                'os_distribution' => [
                    'labels' => array_keys($topOs),
                    'data' => array_values($topOs)
                ]
            ],
            'activity_feed' => $activityFeed
        ]);
    }
}
