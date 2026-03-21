<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Essay;
use App\Models\PlatformEvent;
use App\Models\Simulation;
use App\Models\UserLog;
use App\Models\UserQuestionAnswer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Controller responsible for monitoring engagement metrics
 * such as questions answered, essays completed, and simulations taken.
 */
class PlatformEngagementController extends Controller
{
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
                'today' => UserQuestionAnswer::withoutAdmins()->whereDate('created_at', Carbon::today())->count(),
                'week'  => UserQuestionAnswer::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => UserQuestionAnswer::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        // Aggregate stats for period
        $aggregate = UserQuestionAnswer::withoutAdmins()->where('created_at', '>=', $start)
            ->selectRaw('COUNT(*) as total, SUM(is_correct) as correct, SUM(IF(is_correct=0,1,0)) as wrong')
            ->first();

        $total   = (int) ($aggregate->total ?? 0);
        $correct = (int) ($aggregate->correct ?? 0);
        $wrong   = (int) ($aggregate->wrong ?? 0);

        // Per-user breakdown
        $perUser = UserQuestionAnswer::withoutAdmins()->where('created_at', '>=', $start)
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
                'today' => Simulation::withoutAdmins()->whereDate('created_at', Carbon::today())->count(),
                'week'  => Simulation::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => Simulation::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        $created  = Simulation::withoutAdmins()->where('created_at', '>=', $start)->count();
        $started  = Simulation::withoutAdmins()->where('created_at', '>=', $start)->whereNotNull('started_at')->count();
        $finished = Simulation::withoutAdmins()->where('created_at', '>=', $start)->whereNotNull('finished_at')->count();

        $list = Simulation::withoutAdmins()->where('created_at', '>=', $start)
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
                'today' => Essay::withoutAdmins()->whereDate('created_at', Carbon::today())->count(),
                'week'  => Essay::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(7))->count(),
                'month' => Essay::withoutAdmins()->where('created_at', '>=', Carbon::today()->subDays(30))->count(),
            ];
        });

        $created   = Essay::withoutAdmins()->where('created_at', '>=', $start)->count();
        $submitted = Essay::withoutAdmins()->where('created_at', '>=', $start)->whereNotNull('submitted_at')->count();
        $evaluated = Essay::withoutAdmins()->where('created_at', '>=', $start)->whereNotNull('evaluated_at')->count();

        $list = Essay::withoutAdmins()->where('created_at', '>=', $start)
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

    /**
     * GET /api/v1/admin/platform-monitor/activity
     * Combined feed of the most recent events across the platform.
     */
    public function activity()
    {
        $events = PlatformEvent::withoutAdmins()->with('user:id,name,email,avatar_url')
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
        $recentLogins = UserLog::withoutAdmins()->with('user:id,name,email,avatar_url')
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
}
