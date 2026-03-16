<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Models\PlatformHeartbeat;
use App\Models\PlatformSession;
use App\Models\PlatformEvent;
use App\Models\Simulation;
use App\Models\User;
use App\Models\UserLog;
use App\Models\UserQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AdminPlatformMonitorController
 *
 * All endpoints are admin-only (protected by is.admin middleware).
 * Aggregates data from native platform tracking tables and existing tables:
 *   - platform_sessions, platform_events, platform_heartbeats (new)
 *   - user_logs, user_question_answers, simulations, essays (existing)
 */
class AdminPlatformMonitorController extends Controller
{
    private function onlineThreshold(): Carbon
    {
        return now()->subMinutes(3);
    }

    // =========================================================================
    // OVERVIEW
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/overview
     * Summary cards: users today, online now, questions today, etc.
     */
    public function overview()
    {
        return Cache::remember('platform_monitor_overview', 30, function () {
            $today = Carbon::today();
            $weekAgo = Carbon::today()->subDays(7);

            // Users who logged in today (from user_logs)
            $usersToday = UserLog::where('action', 'login')
                ->whereDate('created_at', $today)
                ->distinct('user_id')
                ->count('user_id');

            // Users currently online (heartbeat within last 3 minutes)
            $onlineNow = PlatformHeartbeat::where('pinged_at', '>=', $this->onlineThreshold())
                ->count();

            // Questions answered today
            $questionsToday = UserQuestionAnswer::whereDate('created_at', $today)->count();

            // Simulations created today
            $simulationsToday = Simulation::whereDate('created_at', $today)->count();

            // Essays created today
            $essaysToday = Essay::whereDate('created_at', $today)->count();

            // Average session duration this week (from platform_sessions)
            $avgSessionSeconds = PlatformSession::whereNotNull('ended_at')
                ->where('started_at', '>=', $weekAgo)
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_seconds')
                ->value('avg_seconds');

            // Total users on platform
            $totalUsers = User::count();

            // Total sessions started today
            $sessionsToday = PlatformSession::whereDate('started_at', $today)->count();

            return response()->json([
                'users_today'        => $usersToday,
                'online_now'         => $onlineNow,
                'questions_today'    => $questionsToday,
                'simulations_today'  => $simulationsToday,
                'essays_today'       => $essaysToday,
                'avg_session_minutes'=> $avgSessionSeconds ? round($avgSessionSeconds / 60, 1) : 0,
                'total_users'        => $totalUsers,
                'sessions_today'     => $sessionsToday,
            ]);
        });
    }

    // =========================================================================
    // ONLINE USERS
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/online
     * Returns list of users currently online with session details.
     */
    public function online()
    {
        $threshold = $this->onlineThreshold();

        $heartbeats = PlatformHeartbeat::where('pinged_at', '>=', $threshold)
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

    // =========================================================================
    // LOGINS
    // =========================================================================

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
                'today' => UserLog::where('action', 'login')
                    ->whereDate('created_at', Carbon::today())
                    ->count(),
                'week'  => UserLog::where('action', 'login')
                    ->where('created_at', '>=', Carbon::today()->subDays(7))
                    ->count(),
                'month' => UserLog::where('action', 'login')
                    ->where('created_at', '>=', Carbon::today()->subDays(30))
                    ->count(),
            ];
        });

        // Detail list for selected period
        $logins = UserLog::with('user:id,name,email,avatar_url')
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

    // =========================================================================
    // QUESTIONS
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/questions?period=today|week|month
     */
    public function questions(Request $request)
    {
        $period = $request->input('period', 'today');

        [$start, $label] = match ($period) {
            'week'  => [Carbon::today()->subDays(7), 'Últimos 7 Dias'],
            'month' => [Carbon::today()->subDays(30), 'Últimos 30 Dias'],
            default => [Carbon::today(), 'Hoje'],
        };

        // Summary counts
        $counts = Cache::remember("platform_questions_counts", 60, function () {
            return [
                'today' => UserQuestionAnswer::whereDate('created_at', Carbon::today())->count(),
                'week'  => UserQuestionAnswer::where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => UserQuestionAnswer::where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        // Aggregate stats for period
        $aggregate = UserQuestionAnswer::where('created_at', '>=', $start)
            ->selectRaw('COUNT(*) as total, SUM(is_correct) as correct, SUM(IF(is_correct=0,1,0)) as wrong')
            ->first();

        $total   = (int) ($aggregate->total ?? 0);
        $correct = (int) ($aggregate->correct ?? 0);
        $wrong   = (int) ($aggregate->wrong ?? 0);

        // Per-user breakdown
        $perUser = UserQuestionAnswer::where('created_at', '>=', $start)
            ->select('user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(is_correct) as correct'),
                DB::raw('SUM(IF(is_correct=0,1,0)) as wrong')
            )
            ->groupBy('user_id')
            ->with('user:id,name,email,avatar_url') // eager load on join
            ->orderByDesc('total')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $user = User::find($row->user_id);
                $acc  = $row->total > 0 ? round(($row->correct / $row->total) * 100, 1) : 0;
                return [
                    'user_id'    => $row->user_id,
                    'name'       => $user->name ?? 'Desconhecido',
                    'email'      => $user->email ?? '',
                    'avatar_url' => $user->avatar_url ?? null,
                    'total'      => (int) $row->total,
                    'correct'    => (int) $row->correct,
                    'wrong'      => (int) $row->wrong,
                    'accuracy'   => $acc,
                ];
            });

        return response()->json([
            'period'      => $period,
            'label'       => $label,
            'counts'      => $counts,
            'total'       => $total,
            'correct'     => $correct,
            'wrong'       => $wrong,
            'accuracy'    => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
            'error_rate'  => $total > 0 ? round(($wrong / $total) * 100, 1) : 0,
            'per_user'    => $perUser,
        ]);
    }

    // =========================================================================
    // SIMULATIONS
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/simulations?period=today|week|month
     */
    public function simulations(Request $request)
    {
        $period = $request->input('period', 'today');

        [$start, $label] = match ($period) {
            'week'  => [Carbon::today()->subDays(7), 'Últimos 7 Dias'],
            'month' => [Carbon::today()->subDays(30), 'Últimos 30 Dias'],
            default => [Carbon::today(), 'Hoje'],
        };

        $counts = Cache::remember("platform_simulations_counts", 60, function () {
            return [
                'today' => Simulation::whereDate('created_at', Carbon::today())->count(),
                'week'  => Simulation::where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => Simulation::where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        $created  = Simulation::where('created_at', '>=', $start)->count();
        $started  = Simulation::where('created_at', '>=', $start)->whereNotNull('started_at')->count();
        $finished = Simulation::where('created_at', '>=', $start)->whereNotNull('finished_at')->count();

        $list = Simulation::where('created_at', '>=', $start)
            ->with('user:id,name,email,avatar_url')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($sim) {
                return [
                    'id'         => $sim->id,
                    'user_id'    => $sim->user_id,
                    'name'       => $sim->user->name ?? 'Desconhecido',
                    'email'      => $sim->user->email ?? '',
                    'avatar_url' => $sim->user->avatar_url ?? null,
                    'type'       => $sim->type,
                    'status'     => $sim->status,
                    'score'      => $sim->score,
                    'created_at' => $sim->created_at->toIso8601String(),
                    'started_at' => $sim->started_at?->toIso8601String(),
                    'finished_at'=> $sim->finished_at?->toIso8601String(),
                    'duration_minutes' => $sim->finished_at && $sim->started_at
                        ? (int) $sim->started_at->diffInMinutes($sim->finished_at)
                        : null,
                ];
            });

        return response()->json([
            'period'   => $period,
            'label'    => $label,
            'counts'   => $counts,
            'created'  => $created,
            'started'  => $started,
            'finished' => $finished,
            'list'     => $list,
        ]);
    }

    // =========================================================================
    // ESSAYS
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/essays?period=today|week|month
     */
    public function essays(Request $request)
    {
        $period = $request->input('period', 'today');

        [$start, $label] = match ($period) {
            'week'  => [Carbon::today()->subDays(7), 'Últimos 7 Dias'],
            'month' => [Carbon::today()->subDays(30), 'Últimos 30 Dias'],
            default => [Carbon::today(), 'Hoje'],
        };

        $counts = Cache::remember("platform_essays_counts", 60, function () {
            return [
                'today' => Essay::whereDate('created_at', Carbon::today())->count(),
                'week'  => Essay::where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => Essay::where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        $created   = Essay::where('created_at', '>=', $start)->count();
        $submitted = Essay::where('created_at', '>=', $start)->whereNotNull('submitted_at')->count();
        $evaluated = Essay::where('created_at', '>=', $start)->whereNotNull('evaluated_at')->count();

        $list = Essay::where('created_at', '>=', $start)
            ->with('user:id,name,email,avatar_url')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($essay) {
                $timeSpent = null;
                if ($essay->submitted_at && $essay->created_at) {
                    $timeSpent = (int) $essay->created_at->diffInMinutes($essay->submitted_at);
                }

                return [
                    'id'           => $essay->id,
                    'user_id'      => $essay->user_id,
                    'name'         => $essay->user->name ?? 'Desconhecido',
                    'email'        => $essay->user->email ?? '',
                    'avatar_url'   => $essay->user->avatar_url ?? null,
                    'title'        => $essay->title,
                    'status'       => $essay->status,
                    'score'        => $essay->score,
                    'created_at'   => $essay->created_at->toIso8601String(),
                    'submitted_at' => $essay->submitted_at?->toIso8601String(),
                    'evaluated_at' => $essay->evaluated_at?->toIso8601String(),
                    'time_spent_minutes' => $timeSpent,
                ];
            });

        return response()->json([
            'period'    => $period,
            'label'     => $label,
            'counts'    => $counts,
            'created'   => $created,
            'submitted' => $submitted,
            'evaluated' => $evaluated,
            'list'      => $list,
        ]);
    }

    // =========================================================================
    // RECENT ACTIVITY FEED
    // =========================================================================

    /**
     * GET /api/v1/admin/platform-monitor/activity
     * Combined feed of the most recent events across the platform.
     */
    public function activity()
    {
        $events = PlatformEvent::with('user:id,name,email,avatar_url')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($e) {
                return [
                    'id'           => $e->id,
                    'user_id'      => $e->user_id,
                    'name'         => $e->user->name ?? 'Desconhecido',
                    'email'        => $e->user->email ?? '',
                    'avatar_url'   => $e->user->avatar_url ?? null,
                    'event_type'   => $e->event_type,
                    'page'         => $e->page,
                    'resource_id'  => $e->resource_id,
                    'resource_type'=> $e->resource_type,
                    'metadata'     => $e->metadata,
                    'created_at'   => $e->created_at->toIso8601String(),
                ];
            });

        // Complement with recent logins not yet in platform_events
        $recentLogins = UserLog::with('user:id,name,email,avatar_url')
            ->where('action', 'login')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id'          => 'login_' . $log->id,
                    'user_id'     => $log->user_id,
                    'name'        => $log->user->name ?? 'Desconhecido',
                    'email'       => $log->user->email ?? '',
                    'avatar_url'  => $log->user->avatar_url ?? null,
                    'event_type'  => 'session.started',
                    'page'        => null,
                    'resource_id' => null,
                    'resource_type' => null,
                    'metadata'    => null,
                    'created_at'  => $log->created_at->toIso8601String(),
                ];
            });

        $feed = $events->concat($recentLogins)
            ->sortByDesc('created_at')
            ->take(60)
            ->values();

        return response()->json(['feed' => $feed]);
    }

    // =========================================================================
    // USER DETAIL
    // =========================================================================

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
            'timeline' => $fullTimeline,
        ]);
    }
}
