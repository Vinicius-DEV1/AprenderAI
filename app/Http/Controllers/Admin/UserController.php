<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_banned', false);
            }
            elseif ($request->status === 'banned') {
                $query->where('is_banned', true);
            }
        }

        $users = $query->with('plan')->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['subscriptions', 'logs' => fn($q) => $q->latest()->take(20)]);

        // AI Metrics
        $aiLogsQuery = \App\Models\AiRequestLog::where('user_id', $user->id);

        $totalAiCost = $aiLogsQuery->sum('estimated_cost');
        $aiRequestCount = $aiLogsQuery->count();

        // Success Rate: defined as requests that don't have "error" in response_text
        // Or more properly if we had a status column. For now let's check for "error" key in JSON or 0 tokens if fails completely.
        $successCount = \App\Models\AiRequestLog::where('user_id', $user->id)
            ->where('tokens_used_total', '>', 0)
            ->count();

        $successRate = $aiRequestCount > 0 ? ($successCount / $aiRequestCount) * 100 : 0;

        // Peak Usage Hour - Cross-database compatibility
        $driverName = \Illuminate\Support\Facades\DB::getDriverName();
        $hourFunc = $driverName === 'sqlite' ? "strftime('%H', created_at)" : "HOUR(created_at)";

        $peakHour = \App\Models\AiRequestLog::where('user_id', $user->id)
            ->selectRaw("$hourFunc as hour, count(*) as count")
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        $promptHistory = \App\Models\AiRequestLog::where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'ai_page');

        $stats = [
            'simulations' => \App\Models\Simulation::where('user_id', $user->id)->count(),
            'essays' => \App\Models\Essay::where('user_id', $user->id)->count(),
            'ai' => [
                'total_cost' => $totalAiCost,
                'request_count' => $aiRequestCount,
                'success_rate' => $successRate,
                'peak_hour' => $peakHour ? $peakHour->hour . ':00' : 'N/A',
            ]
        ];

        return view('admin.users.show', compact('user', 'stats', 'promptHistory'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'                       => 'required|string|max:255',
            'email'                      => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'                      => 'nullable|string|max:20',
            // AI Prompt quota fields
            'ai_questions_count'         => 'nullable|integer|min:0',
            'max_ai_questions_override'  => 'nullable|integer|min:0',
            // Simulation quota override (null = use plan default, 0 = unlimited)
            'max_simulations_override'   => 'nullable|integer|min:0',
            // Essay quota override (null = use plan default, 0 = unlimited)
            'max_essays_override'        => 'nullable|integer|min:0',
        ]);

        // Snapshot before update for audit log
        $original = $user->getOriginal();

        // Build update payload with only fields that were submitted
        $data = $request->only('name', 'email', 'phone');

        // AI quota fields
        if ($request->has('ai_questions_count')) {
            $data['ai_questions_count'] = $request->ai_questions_count;
        }
        if ($request->has('max_ai_questions_override')) {
            // Cast empty string to null so the database stores NULL (= "use plan default")
            $data['max_ai_questions_override'] = $request->filled('max_ai_questions_override')
                ? (int) $request->max_ai_questions_override
                : null;
        }

        // Simulation quota override
        if ($request->has('max_simulations_override')) {
            $data['max_simulations_override'] = $request->filled('max_simulations_override')
                ? (int) $request->max_simulations_override
                : null;
        }

        // Essay quota override
        if ($request->has('max_essays_override')) {
            $data['max_essays_override'] = $request->filled('max_essays_override')
                ? (int) $request->max_essays_override
                : null;
        }

        $user->update($data);

        // Build audit log description from changed fields
        $changes = [];

        if ($original['name'] !== $user->name)
            $changes[] = "Nome: {$original['name']} -> {$user->name}";
        if ($original['email'] !== $user->email)
            $changes[] = "Email: {$original['email']} -> {$user->email}";
        if ($original['phone'] !== $user->phone)
            $changes[] = "Telefone: {$original['phone']} -> {$user->phone}";
        if ($original['ai_questions_count'] != $user->ai_questions_count)
            $changes[] = "Consumo IA: {$original['ai_questions_count']} -> {$user->ai_questions_count}";
        if ($original['max_ai_questions_override'] != $user->max_ai_questions_override)
            $changes[] = "Limite IA Custom: " . ($original['max_ai_questions_override'] ?? 'padrão do plano') . " -> " . ($user->max_ai_questions_override ?? 'padrão do plano');
        if ($original['max_simulations_override'] != $user->max_simulations_override)
            $changes[] = "Limite Simulados Custom: " . ($original['max_simulations_override'] ?? 'padrão do plano') . " -> " . ($user->max_simulations_override ?? 'padrão do plano');
        if ($original['max_essays_override'] != $user->max_essays_override)
            $changes[] = "Limite Redações Custom: " . ($original['max_essays_override'] ?? 'padrão do plano') . " -> " . ($user->max_essays_override ?? 'padrão do plano');

        if (!empty($changes)) {
            UserLog::create([
                'user_id'     => $user->id,
                'action'      => 'admin_update',
                'description' => 'Perfil atualizado por Admin. ' . implode(', ', $changes),
                'ip_address'  => $request->ip(),
            ]);
        }

        return back()->with('success', 'Usuário atualizado com sucesso!');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $user->is_banned = !$user->is_banned;
        $user->save();

        $action = $user->is_banned ? 'ban' : 'unban';
        $desc = $user->is_banned ? 'Usuário banido pelo Admin.' : 'Usuário desbloqueado pelo Admin.';

        UserLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $desc,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Status do usuário alterado!');
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($request->has('send_email')) {
            // Send Reset Link
            $token = Password::createToken($user);
            $user->sendPasswordResetNotification($token);

            UserLog::create([
                'user_id' => $user->id,
                'action' => 'password_reset_email',
                'description' => 'Email de redefinição enviado pelo Admin.',
                'ip_address' => $request->ip(),
            ]);

            return back()->with('success', 'Email de redefinição enviado!');
        }

        if ($request->has('new_password')) {
            $request->validate(['new_password' => 'required|min:8']);

            $user->update(['password' => Hash::make($request->new_password)]);

            UserLog::create([
                'user_id' => $user->id,
                'action' => 'password_change',
                'description' => 'Senha alterada manualmente pelo Admin.',
                'ip_address' => $request->ip(),
            ]);

            return back()->with('success', 'Senha alterada com sucesso!');
        }

        return back()->with('error', 'Ação inválida.');
    }
}
