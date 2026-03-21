<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSession;
use App\Models\PlatformHeartbeat;
use App\Models\UserLog;
use App\Models\User;
use App\Models\UserQuestionAnswer;
use App\Models\Simulation;
use App\Models\Essay;
use App\Models\PlatformEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Controller responsible for monitoring active user sessions,
 * login history, and detailed user activity timelines.
 */
class UserSessionMonitorController extends Controller
{
    private function onlineThreshold(): Carbon
    {
        return now()->subMinutes(5);
    }

    /**
     * GET /api/v1/admin/platform-monitor/online
     * Returns list of users currently online with session details.
     */
    public function online()
    {
        $threshold = $this->onlineThreshold();

        $heartbeats = PlatformHeartbeat::withoutAdmins()->where('pinged_at', '>=', $threshold)
            ->with('user:id,name,email,avatar_url')
            ->orderByDesc('pinged_at')
            ->get();

        $result = $heartbeats->map(function ($hb) {
            // Find associated session
            $session = PlatformSession::where('session_token', $hb->session_token)
                ->whereNull('ended_at')
                ->first();

            return [
                'user_id'        => $hb->user_id,
                'name'           => $hb->user->name ?? 'Desconhecido',
                'email'          => $hb->user->email ?? '',
                'avatar_url'     => $hb->user->avatar_url ?? null,
                'current_page'   => $hb->current_page,
                'last_activity'  => $hb->pinged_at->toIso8601String(),
                'login_at'       => $session?->started_at?->toIso8601String(),
                'online_minutes' => $session ? (int) $session->started_at->diffInMinutes(now()) : null,
                'ip_address'     => $session?->ip_address,
            ];
        });

        return response()->json([
            'count' => $result->count(),
            'users' => $result,
        ]);
    }

    /**
     * GET /api/v1/admin/platform-monitor/logins?period=today|week|month
     * Returns login stats and detailed list.
     */
    public function logins(Request $request)
    {
        $period = $request->input('period', 'today');

        [$start, $label] = match ($period) {
            'week'  => [Carbon::today()->subDays(7), 'Últimos 7 Dias'],
            'month' => [Carbon::today()->subDays(30), 'Últimos 30 Dias'],
            default => [Carbon::today(), 'Hoje'],
        };

        // Summary counts (cached briefly)
        $counts = Cache::remember("platform_logins_counts", 60, function () {
            return [
                'today' => UserLog::withoutAdmins()->where('action', 'login')
                    ->whereDate('created_at', Carbon::today())
                    ->count(),
                'week'  => UserLog::withoutAdmins()->where('action', 'login')
                    ->where('created_at', '>=', Carbon::today()->subDays(7))
                    ->count(),
                'month' => UserLog::withoutAdmins()->where('action', 'login')
                    ->where('created_at', '>=', Carbon::today()->subDays(30))
                    ->count(),
            ];
        });

        // Detail list for selected period
        $logins = UserLog::withoutAdmins()->with('user:id,name,email,avatar_url')
            ->where('action', 'login')
            ->where('created_at', '>=', $start)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function ($log) {
                // Find associated session
                $session = PlatformSession::where('user_id', $log->user_id)
                    ->where('started_at', '>=', Carbon::parse($log->created_at)->subMinutes(1))
                    ->where('started_at', '<=', Carbon::parse($log->created_at)->addMinutes(1))
                    ->first();

                $sessionDuration = null;
                if ($session && $session->ended_at) {
                    $sessionDuration = (int) $session->started_at->diffInMinutes($session->ended_at);
                }

                return [
                    'user_id'          => $log->user_id,
                    'name'             => $log->user->name ?? 'Desconhecido',
                    'email'            => $log->user->email ?? '',
                    'avatar_url'       => $log->user->avatar_url ?? null,
                    'login_at'         => $log->created_at->toIso8601String(),
                    'ip_address'       => $log->ip_address,
                    'session_minutes'  => $sessionDuration,
                    'session_status'   => $session ? ($session->ended_at ? 'encerrada' : 'ativa') : 'desconhecida',
                ];
            });

        return response()->json([
            'period' => $period,
            'label'  => $label,
            'counts' => $counts,
            'list'   => $logins,
        ]);
    }

    /**
     * GET /api/v1/admin/platform-monitor/user/{id}
     * Full activity profile for a single user.
     */
    public function userDetail(int $id)
    {
        $user = User::with(['plan'])->findOrFail($id);

        // Question stats
        $qaStats = UserQuestionAnswer::where('user_id', $id)
            ->selectRaw('COUNT(*) as total, SUM(is_correct) as correct, SUM(IF(is_correct=0,1,0)) as wrong')
            ->first();

        $qaTotal   = (int) ($qaStats->total ?? 0);
        $qaCorrect = (int) ($qaStats->correct ?? 0);
        $qaWrong   = (int) ($qaStats->wrong ?? 0);

        // Simulation stats
        $simTotal    = Simulation::where('user_id', $id)->count();
        $simFinished = Simulation::where('user_id', $id)->whereNotNull('finished_at')->count();
        $simAvgScore = Simulation::where('user_id', $id)->whereNotNull('score')->avg('score');

        // Essay stats
        $essayTotal     = Essay::where('user_id', $id)->count();
        $essaySubmitted = Essay::where('user_id', $id)->whereNotNull('submitted_at')->count();
        $essayEvaluated = Essay::where('user_id', $id)->whereNotNull('evaluated_at')->count();

        // Time on platform (total session seconds, completed sessions)
        $totalSeconds = PlatformSession::where('user_id', $id)
            ->whereNotNull('ended_at')
            ->selectRaw('SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as total_seconds')
            ->value('total_seconds');

        // Average daily activity (days with at least 1 event in past 30 days)
        $activeDays = PlatformEvent::where('user_id', $id)
            ->where('created_at', '>=', Carbon::today()->subDays(30))
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as days')
            ->value('days');

        // Last login
        $lastLogin = UserLog::where('user_id', $id)
            ->where('action', 'login')
            ->orderByDesc('created_at')
            ->first();

        // Activity timeline (last 50 events)
        $timeline = PlatformEvent::where('user_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($e) => [
                'event_type'    => $e->event_type,
                'page'          => $e->page,
                'resource_id'   => $e->resource_id,
                'resource_type' => $e->resource_type,
                'metadata'      => $e->metadata,
                'created_at'    => $e->created_at->toIso8601String(),
            ]);

        // Complement timeline with recent logins
        $recentLogins = UserLog::where('user_id', $id)
            ->where('action', 'login')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($log) => [
                'event_type'    => 'session.started',
                'page'          => null,
                'resource_id'   => null,
                'resource_type' => null,
                'metadata'      => ['ip' => $log->ip_address],
                'created_at'    => $log->created_at->toIso8601String(),
            ]);

        $fullTimeline = $timeline->concat($recentLogins)
            ->sortByDesc('created_at')
            ->take(60)
            ->values();

        // Page distribution (time spent per page)
        $pageDistribution = PlatformEvent::withoutAdmins()
            ->where('user_id', $id)
            ->whereNotNull('duration_seconds')
            ->select('page', DB::raw('SUM(duration_seconds) as total_seconds'))
            ->groupBy('page')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(fn($row) => [
                'page'          => $row->page ?? 'Geral/Outros',
                'total_seconds' => (int) $row->total_seconds,
                'total_minutes' => (int) round($row->total_seconds / 60),
            ]);

        return response()->json([
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'role'       => $user->role,
                'plan'       => $user->plan?->name,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login' => $lastLogin?->created_at?->toIso8601String(),
            ],
            'stats' => [
                'questions_total'    => $qaTotal,
                'questions_correct'  => $qaCorrect,
                'questions_wrong'    => $qaWrong,
                'accuracy'           => $qaTotal > 0 ? round(($qaCorrect / $qaTotal) * 100, 1) : 0,
                'simulations_total'  => $simTotal,
                'simulations_finished'=> $simFinished,
                'sim_avg_score'      => $simAvgScore ? round($simAvgScore, 1) : null,
                'essays_total'       => $essayTotal,
                'essays_submitted'   => $essaySubmitted,
                'essays_evaluated'   => $essayEvaluated,
                'total_time_minutes' => $totalSeconds ? (int) round($totalSeconds / 60) : 0,
                'active_days_30'     => (int) ($activeDays ?? 0),
            ],
            'timeline'          => $fullTimeline,
            'page_distribution' => $pageDistribution,
        ]);
    }
}
