<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutEvent;
use App\Models\Plan;
use App\Models\PurchaseIntention;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin dashboard for checkout conversion funnel and plan performance.
 */
class CheckoutFunnelController extends Controller
{
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
                'count' => CheckoutEvent::withoutAdmins()->where('event_type', 'prices_viewed')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'plan_clicked',
                'label' => 'Clicou em Plano',
                'count' => PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'checkout_opened',
                'label' => 'Abriu Checkout',
                'count' => CheckoutEvent::withoutAdmins()->where('event_type', 'checkout_opened')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'payment_initiated',
                'label' => 'Enviou Pagamento',
                'count' => CheckoutEvent::withoutAdmins()->where('event_type', 'payment_initiated')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
            ],
            [
                'step'  => 'payment_success',
                'label' => 'Pagamento Confirmado',
                'count' => CheckoutEvent::withoutAdmins()->where('event_type', 'payment_success')->where('created_at', '>=', $since)->count(DB::raw('DISTINCT user_id')),
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

        $clicks = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)
            ->select('plan_id', DB::raw('count(*) as total_clicks'))
            ->groupBy('plan_id')
            ->pluck('total_clicks', 'plan_id');

        $conversions = PurchaseIntention::withoutAdmins()->converted()
            ->where('created_at', '>=', $since)
            ->select('plan_id', DB::raw('count(*) as total_conversions'))
            ->groupBy('plan_id')
            ->pluck('total_conversions', 'plan_id');

        $checkouts = CheckoutEvent::withoutAdmins()->where('event_type', 'checkout_opened')
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
}
