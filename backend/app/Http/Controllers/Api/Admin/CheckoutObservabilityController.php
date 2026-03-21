<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckoutAbandonment;
use App\Models\CheckoutError;
use App\Models\CheckoutEvent;
use App\Models\PurchaseIntention;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin dashboard for debugging, tracking timelines, and receiving alerts
 * regarding checkout health.
 */
class CheckoutObservabilityController extends Controller
{
    /**
     * GET /admin/checkout/errors
     * Error ranking by type and frequency.
     */
    public function errors(Request $request): \Illuminate\Http\JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);

        $errorsByType = CheckoutError::withoutAdmins()->where('created_at', '>=', $since)
            ->select('error_type', DB::raw('count(*) as total'), DB::raw('max(created_at) as last_occurred'))
            ->groupBy('error_type')
            ->orderByDesc('total')
            ->get();

        $recentErrors = CheckoutError::withoutAdmins()->with(['user', 'plan'])
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
        $limit = (int) $request->get('limit', 20);
        $since = now()->subDays($days);

        $paginated = CheckoutAbandonment::withoutAdmins()->with(['user', 'plan'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->paginate($limit);


        $paginated->getCollection()->transform(function ($a) use ($since) {
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
            $errorQuery = CheckoutError::withoutAdmins()->where('created_at', '>=', $since);

            if ($userId) {
                $errorQuery->where('user_id', $userId);
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

        return response()->json($paginated);
    }

    /**
     * GET /admin/checkout/alerts
     * Automatic anomaly detection for checkout health.
     */
    public function alerts(): \Illuminate\Http\JsonResponse
    {
        $alerts = [];

        // Alert 1: Many failures of same type in last 2 hours
        $recentErrors = CheckoutError::withoutAdmins()->where('created_at', '>=', now()->subHours(2))
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
        $todayCheckouts  = CheckoutEvent::withoutAdmins()->where('event_type', 'checkout_opened')->whereDate('created_at', today())->count();
        $todayAbandonment = CheckoutAbandonment::withoutAdmins()->whereDate('created_at', today())->count();

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
        $last24hIntentions  = PurchaseIntention::withoutAdmins()->where('created_at', '>=', now()->subDay())->count();
        $last24hConversions = PurchaseIntention::withoutAdmins()->converted()->where('created_at', '>=', now()->subDay())->count();

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
        $limit = (int) $request->get('limit', 20);

        $events = CheckoutEvent::withoutAdmins()->with(['user', 'plan'])
            ->orderByDesc('created_at')
            ->paginate($limit);


        $events->getCollection()->transform(fn($e) => [
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

        return response()->json($events);
    }
}
