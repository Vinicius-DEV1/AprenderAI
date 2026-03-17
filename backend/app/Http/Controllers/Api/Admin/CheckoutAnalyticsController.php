<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutAbandonment;
use App\Models\CheckoutError;
use App\Models\CheckoutEvent;
use App\Models\Plan;
use App\Models\PurchaseIntention;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin dashboard for checkout observability.
 * Provides KPIs, conversion funnel, error ranking, user timeline, and alerts.
 */
class CheckoutAnalyticsController extends Controller
{
    /**
     * GET /admin/checkout/overview
     * Main KPIs: intentions, checkouts, conversions, failures, abandonments, conversion rate.
     */
    public function overview(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $intentions       = PurchaseIntention::where('created_at', '>=', $since)->count();
        $uniqueIntentions = PurchaseIntention::where('created_at', '>=', $since)->count(DB::raw('DISTINCT COALESCE(user_id, ip)'));
        $gainedRevenue    = PurchaseIntention::converted()->where('created_at', '>=', $since)->sum('plan_amount');
        $lostRevenue      = PurchaseIntention::abandoned()->where('created_at', '>=', $since)->sum('plan_amount');

        $checkoutsOpened    = CheckoutEvent::where('event_type', 'checkout_opened')->where('created_at', '>=', $since)->count();
        $paymentsInitiated  = CheckoutEvent::where('event_type', 'payment_initiated')->where('created_at', '>=', $since)->count();
        $paymentsSuccess    = CheckoutEvent::where('event_type', 'payment_success')->where('created_at', '>=', $since)->count();
        $paymentsFailed     = CheckoutEvent::where('event_type', 'payment_failed')->where('created_at', '>=', $since)->count();
        $pricesViewed       = CheckoutEvent::where('event_type', 'prices_viewed')->where('created_at', '>=', $since)->count();

        $abandonments       = CheckoutAbandonment::where('created_at', '>=', $since)->count();
        $abandonmentsCoupon = CheckoutAbandonment::where('created_at', '>=', $since)->where('had_coupon', true)->count();
        $conversionsCoupon  = CheckoutEvent::where('event_type', 'payment_success')
                                ->where('created_at', '>=', $since)
                                ->where('metadata->hadCoupon', true)
                                ->count();

        $conversionRate = $intentions > 0
            ? round(($paymentsSuccess / $intentions) * 100, 2)
            : 0;

        // Average time to convert (in minutes)
        $avgTimeToConvert = PurchaseIntention::converted()
            ->where('created_at', '>=', $since)
            ->whereNotNull('time_to_convert_seconds')
            ->avg('time_to_convert_seconds');

        // V2 Metrics: Devices & Origins
        $devices = PurchaseIntention::where('created_at', '>=', $since)
            ->whereNotNull('device')
            ->select('device', DB::raw('count(*) as intentions'), DB::raw('sum(case when status = "converted" then 1 else 0 end) as conversions'))
            ->groupBy('device')
            ->get();

        $topOrigins = PurchaseIntention::where('created_at', '>=', $since)
            ->whereNotNull('source_page')
            ->select('source_page', DB::raw('count(*) as total'))
            ->groupBy('source_page')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // V3: Approval Rates by Method
        $approvalRates = CheckoutEvent::whereIn('event_type', ['payment_success', 'payment_failed'])
            ->where('created_at', '>=', $since)
            ->whereNotNull('payment_method')
            ->select('payment_method', 
                DB::raw('sum(case when event_type = "payment_success" then 1 else 0 end) as success'),
                DB::raw('count(*) as total')
            )
            ->groupBy('payment_method')
            ->get()
            ->map(function($item) {
                $item->rate = $item->total > 0 ? round(($item->success / $item->total) * 100, 1) : 0;
                return $item;
            });

        // V3: Top regions by IP (Proxy for Geo)
        $topRegions = PurchaseIntention::where('created_at', '>=', $since)
            ->whereNotNull('ip')
            ->select('ip', DB::raw('count(*) as total'))
            ->groupBy('ip')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'period_days'           => $days,
            'prices_viewed'         => $pricesViewed,
            'purchase_intentions'   => $intentions,
            'unique_intentions'     => $uniqueIntentions,
            'gained_revenue'        => (float) $gainedRevenue,
            'lost_revenue'          => (float) $lostRevenue,
            'checkouts_opened'      => $checkoutsOpened,
            'payments_initiated'    => $paymentsInitiated,
            'payments_success'      => $paymentsSuccess,
            'payments_failed'       => $paymentsFailed,
            'abandonments'          => $abandonments,
            'abandonments_coupon'   => $abandonmentsCoupon,
            'conversions_coupon'    => $conversionsCoupon,
            'conversion_rate'       => $conversionRate,
            'avg_time_to_convert_minutes' => $avgTimeToConvert ? round($avgTimeToConvert / 60, 1) : null,
            'devices'               => $devices,
            'top_origins'           => $topOrigins,
            'approval_rates'        => $approvalRates,
            'top_regions'           => $topRegions, // Note: In a real env, this would be State/City
        ]);
    }

    /**
     * GET /admin/checkout/funnel
     * Step-by-step funnel data for the conversion visualization.
     */
    public function funnel(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $steps = [
            [
                'step'  => 'prices_viewed',
                'label' => 'Visualizou Preços',
                'count' => CheckoutEvent::where('event_type', 'prices_viewed')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'plan_clicked',
                'label' => 'Clicou em Plano',
                'count' => PurchaseIntention::where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'checkout_opened',
                'label' => 'Abriu Checkout',
                'count' => CheckoutEvent::where('event_type', 'checkout_opened')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'payment_initiated',
                'label' => 'Enviou Pagamento',
                'count' => CheckoutEvent::where('event_type', 'payment_initiated')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'payment_success',
                'label' => 'Pagamento Confirmado',
                'count' => CheckoutEvent::where('event_type', 'payment_success')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
        ];

        // Calculate drop-off rates between steps
        $funnelWithDropoff = [];
        for ($i = 0; $i < count($steps); $i++) {
            $step = $steps[$i];
            $prev = $i > 0 ? $steps[$i - 1]['count'] : $step['count'];
            $dropoff = $prev > 0 ? round((1 - ($step['count'] / max(1, $prev))) * 100, 1) : 0;
            $funnelWithDropoff[] = array_merge($step, [
                'dropoff_pct' => $i > 0 ? $dropoff : null,
            ]);
        }

        return response()->json(['funnel' => $funnelWithDropoff]);
    }

    /**
     * GET /admin/checkout/plans-ranking
     * Plans sorted by clicks, checkouts, and conversions.
     */
    public function plansRanking(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $plans = Plan::all()->keyBy('id');

        $clicks = PurchaseIntention::where('created_at', '>=', $since)
            ->select('plan_id', DB::raw('count(*) as total_clicks'))
            ->groupBy('plan_id')
            ->pluck('total_clicks', 'plan_id');

        $conversions = PurchaseIntention::converted()
            ->where('created_at', '>=', $since)
            ->select('plan_id', DB::raw('count(*) as total_conversions'))
            ->groupBy('plan_id')
            ->pluck('total_conversions', 'plan_id');

        $checkouts = CheckoutEvent::where('event_type', 'checkout_opened')
            ->where('created_at', '>=', $since)
            ->whereNotNull('plan_id')
            ->select('plan_id', DB::raw('count(*) as total_checkouts'))
            ->groupBy('plan_id')
            ->pluck('total_checkouts', 'plan_id');

        $ranking = $plans->map(function ($plan) use ($clicks, $conversions, $checkouts) {
            $c = (int) ($clicks[$plan->id] ?? 0);
            $conv = (int) ($conversions[$plan->id] ?? 0);
            return [
                'plan_id'          => $plan->id,
                'plan_name'        => $plan->name,
                'plan_interval'    => $plan->interval,
                'plan_price'       => $plan->price,
                'clicks'           => $c,
                'checkouts'        => (int) ($checkouts[$plan->id] ?? 0),
                'conversions'      => $conv,
                'conversion_rate'  => $c > 0 ? round(($conv / $c) * 100, 1) : 0,
            ];
        })->sortByDesc('clicks')->values();

        return response()->json(['ranking' => $ranking]);
    }

    /**
     * GET /admin/checkout/errors
     * Error ranking by type and frequency.
     */
    public function errors(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $errorsByType = CheckoutError::where('created_at', '>=', $since)
            ->select('error_type', DB::raw('count(*) as total'), DB::raw('max(created_at) as last_occurred'))
            ->groupBy('error_type')
            ->orderByDesc('total')
            ->get();

        $recentErrors = CheckoutError::with(['user', 'plan'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'error_type'     => $e->error_type,
                'error_message'  => substr($e->error_message, 0, 200),
                'plan_name'      => $e->plan?->name,
                'user_email'     => $e->user?->email,
                'payment_method' => $e->payment_method,
                'device'         => $e->device,
                'created_at'     => $e->created_at->toDateTimeString(),
            ]);

        return response()->json([
            'by_type'       => $errorsByType,
            'recent_errors' => $recentErrors,
        ]);
    }

    /**
     * GET /admin/checkout/user-timeline/{userId}
     * Full chronological checkout timeline for a specific user.
     */
    public function userTimeline(int $userId): \Illuminate\Http\JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Usuário não encontrado.'], 404);
        }

        $events = CheckoutEvent::where('user_id', $userId)
            ->with('plan')
            ->orderBy('created_at')
            ->get()
            ->map(fn($e) => [
                'timestamp'      => $e->created_at->toDateTimeString(),
                'event_type'     => $e->event_type,
                'plan_name'      => $e->plan?->name ?? (
                    ($e->event_type === 'prices_viewed' && isset($e->metadata['source_page'])) 
                        ? 'Página: ' . $e->metadata['source_page'] 
                        : null
                ),
                'checkout_step'  => $e->checkout_step,
                'payment_method' => $e->payment_method,
                'metadata'       => $e->metadata,
            ]);

        $intentions = PurchaseIntention::where('user_id', $userId)
            ->with('plan')
            ->orderBy('created_at')
            ->get()
            ->map(fn($i) => [
                'timestamp'               => $i->created_at->toDateTimeString(),
                'event_type'              => 'purchase_intention',
                'status'                  => $i->status,
                'plan_name'               => $i->plan?->name,
                'plan_amount'             => $i->plan_amount,
                'source_page'             => $i->source_page,
                'time_to_convert_minutes' => $i->time_to_convert_seconds
                    ? round($i->time_to_convert_seconds / 60, 1)
                    : null,
            ]);

        $errors = CheckoutError::where('user_id', $userId)
            ->with('plan')
            ->orderBy('created_at')
            ->get()
            ->map(fn($e) => [
                'timestamp'   => $e->created_at->toDateTimeString(),
                'event_type'  => 'error:' . $e->error_type,
                'error_message' => substr($e->error_message, 0, 300),
                'plan_name'   => $e->plan?->name,
            ]);

        $abandonments = CheckoutAbandonment::where('user_id', $userId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($a) => [
                'timestamp'        => $a->created_at->toDateTimeString(),
                'event_type'       => 'abandonment',
                'last_step'        => $a->last_step_reached,
                'time_spent_mins'  => $a->time_spent_seconds ? round($a->time_spent_seconds / 60, 1) : null,
            ]);

        // Merge and sort all events chronologically
        $timeline = $events
            ->merge($intentions)
            ->merge($errors)
            ->merge($abandonments)
            ->sortBy('timestamp')
            ->values();

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'timeline' => $timeline,
        ]);
    }

    /**
     * GET /admin/checkout/abandonments
     * Recent abandonment list.
     */
    public function abandonments(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $items = CheckoutAbandonment::with(['user', 'plan'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(function ($a) use ($since) {
                // Determine Identity
                $userName = 'Visitante Anônimo';
                $userEmail = '-';
                $userAvatar = null;
                $userId = null;

                if ($a->user) {
                    $userId = $a->user->id;
                    $userName = $a->user->name;
                    $userEmail = $a->user->email;
                    $userAvatar = $a->user->avatar ?? null;
                } else {
                    $userName = "Visitante (IP: " . ($a->ip ?? 'Desconhecido') . ")";
                }
                
                // Fetch recent errors for this user/IP to show "Attempts" history
                $errorQuery = CheckoutError::where('created_at', '>=', $since);
                if ($userId) {
                    $errorQuery->where('user_id', $userId);
                } else if ($a->session_id) {
                     // We don't have session_id on abandonment right now, so we skip exact anonymous trace
                     // But we can fallback to IP for visitors if we add it in the future.
                } else {
                     $errorQuery->whereRaw('1 = 0'); // empty
                }

                $errors = [];
                if ($userId) {
                    $errors = $errorQuery->select('error_type', DB::raw('count(*) as count'))
                                     ->groupBy('error_type')
                                     ->get()
                                     ->toArray();
                }

                return [
                    'id'               => $a->id,
                    'user_id'          => $userId,
                    'user_name'        => $userName,
                    'user_email'       => $userEmail,
                    'user_avatar'      => $userAvatar,
                    'plan_name'        => $a->plan?->name ?? 'N/A',
                    'last_step'        => $a->last_step_reached,
                    'time_spent_mins'  => $a->time_spent_seconds ? round($a->time_spent_seconds / 60, 1) : null,
                    'payment_method'   => $a->payment_method_selected,
                    'had_coupon'       => $a->had_coupon,
                    'error_history'    => $errors,
                    'created_at'       => $a->created_at->toDateTimeString(),
                ];
            });

        return response()->json(['abandonments' => $items]);
    }

    /**
     * GET /admin/checkout/alerts
     * Automatic anomaly detection for checkout health.
     */
    public function alerts(): \Illuminate\Http\JsonResponse
    {
        $alerts = [];

        // Alert 1: Many failures of same type in last 2 hours
        $recentErrors = CheckoutError::where('created_at', '>=', now()->subHours(2))
            ->select('error_type', DB::raw('count(*) as total'))
            ->groupBy('error_type')
            ->having('total', '>=', 5)
            ->get();

        foreach ($recentErrors as $err) {
            $alerts[] = [
                'type'     => 'high_error_rate',
                'severity' => $err->total >= 20 ? 'critical' : 'warning',
                'message'  => "Muitas falhas do tipo \"{$err->error_type}\" nas últimas 2 horas ({$err->total} ocorrências).",
                'count'    => $err->total,
                'detail'   => $err->error_type,
            ];
        }

        // Alert 2: High abandonment rate today
        $todayCheckouts  = CheckoutEvent::where('event_type', 'checkout_opened')->whereDate('created_at', today())->count();
        $todayAbandonment = CheckoutAbandonment::whereDate('created_at', today())->count();
        if ($todayCheckouts >= 5 && $todayAbandonment > 0) {
            $abandonmentRate = round(($todayAbandonment / $todayCheckouts) * 100, 1);
            if ($abandonmentRate >= 70) {
                $alerts[] = [
                    'type'     => 'high_abandonment',
                    'severity' => 'warning',
                    'message'  => "Taxa de abandono alta hoje: {$abandonmentRate}% ({$todayAbandonment} de {$todayCheckouts} checkouts abandonados).",
                    'count'    => $todayAbandonment,
                    'detail'   => "{$abandonmentRate}%",
                ];
            }
        }

        // Alert 3: Zero conversions in the last 24h (with enough intent activity)
        $last24hIntentions  = PurchaseIntention::where('created_at', '>=', now()->subDay())->count();
        $last24hConversions = PurchaseIntention::converted()->where('created_at', '>=', now()->subDay())->count();
        if ($last24hIntentions >= 10 && $last24hConversions === 0) {
            $alerts[] = [
                'type'     => 'zero_conversions',
                'severity' => 'critical',
                'message'  => "Nenhuma conversão nas últimas 24h apesar de {$last24hIntentions} intenções de compra.",
                'count'    => $last24hIntentions,
                'detail'   => '0 conversões',
            ];
        }

        return response()->json([
            'alerts' => $alerts,
            'total'  => count($alerts),
        ]);
    }

    /**
     * GET /admin/checkout/timeline
     * Live activity feed of recent checkout events.
     */
    public function timeline(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = (int) $request->get('limit', 50);

        $events = CheckoutEvent::with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'event_type'     => $e->event_type,
                'user_name'      => $e->user?->name ?? 'Visitante Anônimo',
                'user_avatar'    => $e->user?->avatar ?? null,
                'plan_name'      => $e->plan?->name ?? (
                    ($e->event_type === 'prices_viewed' && isset($e->metadata['source_page'])) 
                        ? 'Página: ' . $e->metadata['source_page'] 
                        : 'N/A'
                ),
                'payment_method' => $e->payment_method,
                'metadata'       => $e->metadata,
                'created_at'     => $e->created_at->toDateTimeString(),
                'time_ago'       => $e->created_at->diffForHumans(),
            ]);

        return response()->json(['events' => $events]);
    }
}
