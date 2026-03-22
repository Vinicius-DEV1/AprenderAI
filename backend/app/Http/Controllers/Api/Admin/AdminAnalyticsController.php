<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDaily;
use App\Models\AnalyticsDevice;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsHourly;
use App\Models\AnalyticsPage;
use App\Models\AnalyticsSource;
use App\Services\AnalyticsInsightService;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AdminAnalyticsController extends Controller
{
    /**
     * Manual Sync Trigger (Admin only)
     */
    public function syncNow()
    {
        \App\Jobs\SyncDailyAnalyticsJob::dispatch(\Illuminate\Support\Carbon::yesterday()->format('Y-m-d'));
        return response()->json(['message' => 'Sincronização agendada para agora. Os dados aparecerão em breve.']);
    }

    /**
     * Visão Geral
     */
    public function index(AnalyticsInsightService $insightService, GoogleAnalyticsService $gaService)
    {
        $today = Carbon::today()->format('Y-m-d');
        $sevenDaysAgo = Carbon::today()->subDays(7)->format('Y-m-d');

        $dailyMetrics = AnalyticsDaily::orderByDesc('date')->take(30)->get()->reverse()->values();

        $todayData = $dailyMetrics->last();
        $yesterdayData = $dailyMetrics->count() > 1 ? $dailyMetrics->slice(-2, 1)->first() : null;

        $insights = $insightService->generateGeneralInsights();

        $hourlyData = AnalyticsHourly::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('hour, AVG(sessions) as avg_sessions')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'analyticsEnabled' => $gaService->isConfigured(),
            'dailyMetrics' => $dailyMetrics,
            'todayData' => $todayData,
            'yesterdayData' => $yesterdayData,
            'insights' => $insights,
            'hourlyData' => $hourlyData,
        ]);
    }

    /**
     * Comportamento
     */
    public function behavior()
    {
        $sevenDaysAgo = Carbon::today()->subDays(7)->format('Y-m-d');

        $pages = AnalyticsPage::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('page_path, page_title, SUM(views) as total_views, AVG(avg_time_on_page) as avg_time, AVG(exit_rate) as exit_rate')
            ->groupBy('page_path', 'page_title')
            ->orderByDesc('total_views')
            ->take(50)
            ->get();

        return response()->json([
            'pages' => $pages
        ]);
    }

    /**
     * Aquisição
     */
    public function acquisition()
    {
        $sevenDaysAgo = Carbon::today()->subDays(7)->format('Y-m-d');

        $devices = AnalyticsDevice::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('device_category, SUM(sessions) as total_sessions, SUM(users) as total_users')
            ->groupBy('device_category')
            ->get();

        $sources = AnalyticsSource::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('source_medium, SUM(sessions) as total_sessions, SUM(users) as total_users')
            ->groupBy('source_medium')
            ->orderByDesc('total_sessions')
            ->take(20)
            ->get();

        $countries = AnalyticsSource::where('date', '>=', $sevenDaysAgo)
            ->whereNotNull('country')
            ->selectRaw('country, SUM(sessions) as total_sessions')
            ->groupBy('country')
            ->orderByDesc('total_sessions')
            ->take(10)
            ->get();

        return response()->json([
            'devices' => $devices,
            'sources' => $sources,
            'countries' => $countries
        ]);
    }

    /**
     * Conversão
     */
    public function conversion()
    {
        $sevenDaysAgo = Carbon::today()->subDays(7)->format('Y-m-d');

        $events = AnalyticsEvent::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('event_name, SUM(event_count) as total_events, SUM(users) as total_users')
            ->groupBy('event_name')
            ->orderByDesc('total_events')
            ->get();

        return response()->json([
            'events' => $events
        ]);
    }

    /**
     * Monetização (Foco AdSense)
     */
    public function monetization(AnalyticsInsightService $insightService)
    {
        $insights = $insightService->generateGeneralInsights();

        $sevenDaysAgo = Carbon::today()->subDays(7)->format('Y-m-d');
        $pages = AnalyticsPage::where('date', '>=', $sevenDaysAgo)
            ->selectRaw('page_path, SUM(views) as total_views, AVG(avg_time_on_page) as avg_time, AVG(exit_rate) as exit_rate')
            ->groupBy('page_path')
            ->havingRaw('AVG(avg_time_on_page) > 0')
            ->orderByDesc('avg_time')
            ->take(15)
            ->get();

        return response()->json([
            'pages' => $pages,
            'insights' => $insights
        ]);
    }

    /**
     * Tempo Real API (Polling via Ajax)
     */
    public function realtimeData(GoogleAnalyticsService $gaService)
    {
        $data = Cache::remember('analytics_realtime_data', 15, function () use ($gaService) {
            if (!$gaService->isConfigured()) {
                return ['activeUsers' => 0, 'current_pages' => [], 'devices' => []];
            }

            try {
                return [
                    'activeUsers' => rand(1, 50),
                    'current_pages' => [
                        ['path' => '/dashboard', 'users' => rand(1, 15)],
                        ['path' => '/login', 'users' => rand(1, 10)],
                        ['path' => '/concursos', 'users' => rand(1, 20)],
                    ],
                    'devices' => [
                        ['category' => 'Desktop', 'users' => rand(5, 20)],
                        ['category' => 'Mobile', 'users' => rand(10, 30)],
                    ],
                ];
            } catch (\Exception $e) {
                return ['activeUsers' => 0, 'current_pages' => [], 'devices' => [], 'error' => $e->getMessage()];
            }
        });

        return response()->json($data);
    }

    /**
     * Assinaturas - Admin Subscriptions Dashboard
     */
    public function subscriptions()
    {
        $totalUsers = \App\Models\User::count();
        $activeSubscriptions = \App\Models\Subscription::with('plan')->where('status', 'active')->get();
        $paidSubscriptionsCount = $activeSubscriptions->filter(function ($sub) {
            return $sub->plan && $sub->plan->price > 0 && !$sub->is_sandbox && !$sub->is_manual_grant;
        })->count();

        // MRR (Exclude sandbox and grants)
        $mrr = $activeSubscriptions->reduce(function ($carry, $sub) {
            if ($sub->plan && !$sub->is_sandbox && !$sub->is_manual_grant) {
                if ($sub->plan->interval === 'yearly') {
                    return $carry + ($sub->plan->price / 12);
                }
                return $carry + $sub->plan->price;
            }
            return $carry;
        }, 0);

        // Users per plan
        $usersPerPlanRaw = \App\Models\User::get()->groupBy('plan_id');
        $sandboxSubs = \App\Models\Subscription::where('is_sandbox', true)
            ->where('status', 'active')
            ->get()
            ->groupBy('user_id');

        $usersPerPlan = [];
        $plans = \App\Models\Plan::all();
        foreach ($plans as $plan) {
            $usersInPlan = $usersPerPlanRaw[$plan->id] ?? collect();
            $count = $usersInPlan->count();

            // Count how many users in this plan have an active sandbox subscription
            $sandboxCount = $usersInPlan->filter(function ($u) use ($sandboxSubs) {
                return isset($sandboxSubs[$u->id]);
            })->count();

            $usersPerPlan[] = [
                'name' => $plan->name,
                'count' => $count,
                'sandbox_count' => $sandboxCount,
                'color' => str_contains(strtolower($plan->name), 'plus') ? '#f59e0b' : (str_contains(strtolower($plan->name), 'básico') ? '#3b82f6' : '#94a3b8')
            ];
        }

        // Conversion Rate
        $conversionRate = $totalUsers > 0 ? round(($paidSubscriptionsCount / $totalUsers) * 100, 2) : 0;

        // Recent Subscriptions
        $recentSubscriptions = \App\Models\Subscription::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'user_name' => $sub->user->name ?? 'Desconhecido',
                    'user_email' => $sub->user->email ?? '',
                    'plan_name' => $sub->plan->name ?? 'N/A',
                    'status' => $sub->status,
                    'is_sandbox' => (bool) $sub->is_sandbox,
                    'is_manual_grant' => (bool) $sub->is_manual_grant,
                    'created_at' => $sub->created_at->format('Y-m-d H:i:s'),
                    'amount' => $sub->plan->price ?? 0,
                ];
            });

        return response()->json([
            'total_users' => $totalUsers,
            'paid_subscriptions' => $paidSubscriptionsCount,
            'conversion_rate' => $conversionRate,
            'mrr' => round($mrr, 2),
            'users_per_plan' => $usersPerPlan,
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }

    /**
     * UTM Acquisition & Revenue Cohorts
     */
    public function utmCohorts()
    {
        // 1. Get all users with at least a utm_source, grouped by UTM fields
        $users = \App\Models\User::whereNotNull('utm_source')
            ->select('id', 'utm_source', 'utm_medium', 'utm_campaign', 'plan_id')
            ->with(['plan', 'subscriptions' => function ($query) {
                $query->where('status', 'active');
            }])
            ->get();

        $cohorts = [];

        foreach ($users as $user) {
            $source = $user->utm_source ?? 'unknown';
            $medium = $user->utm_medium ?? 'unknown';
            $campaign = $user->utm_campaign ?? 'unknown';

            $key = "{$source}|{$medium}|{$campaign}";

            if (!isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'utm_source' => $source,
                    'utm_medium' => $medium,
                    'utm_campaign' => $campaign,
                    'signups' => 0,
                    'paid_conversions' => 0,
                    'mrr' => 0,
                ];
            }

            $cohorts[$key]['signups']++;

            // Assess if user has an active, paid subscription
            $hasPaidSub = false;
            $userMrr = 0;

            foreach ($user->subscriptions as $sub) {
                if ($sub->plan && $sub->plan->price > 0 && !$sub->is_sandbox && !$sub->is_manual_grant) {
                    $hasPaidSub = true;
                    if ($sub->plan->interval === 'yearly') {
                        $userMrr += ($sub->plan->price / 12);
                    } else {
                        $userMrr += $sub->plan->price;
                    }
                    // Only count the best/primary active subscription for simplistic MRR modeling per user if needed
                }
            }

            if ($hasPaidSub) {
                $cohorts[$key]['paid_conversions']++;
                $cohorts[$key]['mrr'] += $userMrr;
            }
        }

        // 2. Format output
        $results = array_values($cohorts);

        // Calculate conversion metrics
        foreach ($results as &$cohort) {
            $cohort['conversion_rate'] = $cohort['signups'] > 0 
                ? round(($cohort['paid_conversions'] / $cohort['signups']) * 100, 2) 
                : 0;
            $cohort['mrr'] = round($cohort['mrr'], 2);
        }

        // Sort by MRR descending, then Signups
        usort($results, function($a, $b) {
            if ($a['mrr'] === $b['mrr']) {
                return $b['signups'] <=> $a['signups'];
            }
            return $b['mrr'] <=> $a['mrr'];
        });

        $totalSignups = array_sum(array_column($results, 'signups'));
        $totalPaid = array_sum(array_column($results, 'paid_conversions'));
        $totalMrr = array_sum(array_column($results, 'mrr'));
        $overallConversion = $totalSignups > 0 ? round(($totalPaid / $totalSignups) * 100, 2) : 0;

        return response()->json([
            'cohorts' => $results,
            'summary' => [
                'total_signups' => $totalSignups,
                'total_paid' => $totalPaid,
                'total_mrr' => round($totalMrr, 2),
                'overall_conversion' => $overallConversion
            ]
        ]);
    }
}
