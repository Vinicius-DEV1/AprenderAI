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
        $since = \Carbon\Carbon::now()->subDays($days);

        $intentions       = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)->count();
        $uniqueIntentions = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)->count(DB::raw('DISTINCT COALESCE(user_id, ip)'));
        $gainedRevenue    = PurchaseIntention::withoutAdmins()->converted()->where('created_at', '>=', $since)->sum('plan_amount');
        $lostRevenue      = PurchaseIntention::withoutAdmins()->abandoned()->where('created_at', '>=', $since)->sum('plan_amount');


        $checkoutsOpened    = CheckoutEvent::withoutAdmins()->where('event_type', 'checkout_opened')->where('created_at', '>=', $since)->count();
        $paymentsInitiated  = CheckoutEvent::withoutAdmins()->where('event_type', 'payment_initiated')->where('created_at', '>=', $since)->count();
        $paymentsSuccess    = CheckoutEvent::withoutAdmins()->where('event_type', 'payment_success')->where('created_at', '>=', $since)->count();
        $paymentsFailed     = CheckoutEvent::withoutAdmins()->where('event_type', 'payment_failed')->where('created_at', '>=', $since)->count();
        $pricesViewed       = CheckoutEvent::withoutAdmins()->where('event_type', 'prices_viewed')->where('created_at', '>=', $since)->count();
        
        $abandonments       = CheckoutAbandonment::withoutAdmins()->where('created_at', '>=', $since)->count();
        $abandonmentsCoupon = CheckoutAbandonment::withoutAdmins()->where('created_at', '>=', $since)->where('had_coupon', true)->count();

        $conversionsCoupon  = CheckoutEvent::withoutAdmins()->where('event_type', 'payment_success')
                                ->where('created_at', '>=', $since)
                                ->where('metadata->hadCoupon', true)
                                ->count();


        $conversionRate = $intentions > 0
            ? round(($paymentsSuccess / $intentions) * 100, 2)
            : 0;

        // Average time to convert (in minutes)
        $avgTimeToConvert = PurchaseIntention::withoutAdmins()->converted()
            ->where('created_at', '>=', $since)
            ->whereNotNull('time_to_convert_seconds')
            ->avg('time_to_convert_seconds');


        // V2 Metrics: Devices & Origins
        $devices = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)
            ->whereNotNull('device')
            ->select('device', DB::raw('count(*) as intentions'), DB::raw('sum(case when status = "converted" then 1 else 0 end) as conversions'))
            ->groupBy('device')
            ->get();


        $topOrigins = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)
            ->whereNotNull('source_page')
            ->select('source_page', DB::raw('count(*) as total'))
            ->groupBy('source_page')
            ->orderByDesc('total')
            ->take(5)
            ->get();


        // V3: Approval Rates by Method
        $approvalRates = CheckoutEvent::withoutAdmins()->whereIn('event_type', ['payment_success', 'payment_failed'])
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
        $topRegions = PurchaseIntention::withoutAdmins()->where('created_at', '>=', $since)
            ->whereNotNull('ip')
            ->select('ip', DB::raw('count(*) as total'))
            ->groupBy('ip')
            ->orderByDesc('total')
            ->take(10)
            ->get();


        return \response()->json([
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
}
