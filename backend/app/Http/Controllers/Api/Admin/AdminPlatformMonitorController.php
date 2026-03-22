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
    private function onlineThreshold(): \Illuminate\Support\Carbon
    {
        return now()->subMinutes(5);
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
            $today = \Illuminate\Support\Carbon::today();
            $weekAgo = \Illuminate\Support\Carbon::today()->subDays(7);

            // Users who logged in today (from user_logs)
            $usersToday = UserLog::withoutAdmins()->where('action', 'login')
                ->whereDate('created_at', $today)
                ->distinct('user_id')
                ->count('user_id');


            // Users currently online (heartbeat within last 3 minutes)
            $onlineNow = PlatformHeartbeat::withoutAdmins()->where('pinged_at', '>=', $this->onlineThreshold())
                ->count();


            // Questions answered today
            $questionsToday = UserQuestionAnswer::withoutAdmins()->whereDate('created_at', $today)->count();


            // Simulations created today
            $simulationsToday = Simulation::withoutAdmins()->whereDate('created_at', $today)->count();


            // Essays created today
            $essaysToday = Essay::withoutAdmins()->whereDate('created_at', $today)->count();


            // Average session duration this week (from platform_sessions)
            $avgSessionSeconds = PlatformSession::withoutAdmins()->whereNotNull('ended_at')
                ->where('started_at', '>=', $weekAgo)
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as avg_seconds')
                ->value('avg_seconds');


            // Total users on platform
            $totalUsers = User::withoutAdmins()->count();


            // Total sessions started today
            $sessionsToday = PlatformSession::withoutAdmins()->whereDate('started_at', $today)->count();

            // Top Engaged Users this week (by total session duration)
            $topEngagedUsers = PlatformSession::withoutAdmins()
                ->where('started_at', '>=', $weekAgo)
                ->whereNotNull('ended_at')
                ->select('user_id', DB::raw('SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as total_seconds'))
                ->groupBy('user_id')
                ->with('user:id,name,email,avatar_url')
                ->orderByDesc('total_seconds')
                ->take(5)
                ->get()
                ->map(fn($row) => [
                    'user_id'       => $row->user_id,
                    'name'          => $row->user->name ?? 'Desconhecido',
                    'email'         => $row->user->email ?? '',
                    'avatar_url'    => $row->user->avatar_url ?? null,
                    'total_minutes' => (int) round($row->total_seconds / 60),
                ]);

            return \response()->json([
                'users_today'        => $usersToday,
                'online_now'         => $onlineNow,
                'questions_today'    => $questionsToday,
                'simulations_today'  => $simulationsToday,
                'essays_today'       => $essaysToday,
                'avg_session_minutes'=> $avgSessionSeconds ? round($avgSessionSeconds / 60, 1) : 0,
                'total_users'        => $totalUsers,
                'sessions_today'     => $sessionsToday,
                'top_engaged_users'  => $topEngagedUsers,
            ]);
        });
    }
}
