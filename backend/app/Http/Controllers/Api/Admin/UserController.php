<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(Request $request)
    {
        $query = User::with(['plan'])
            ->withCount(['simulations', 'essays']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            if ($request->status === 'banned') {
                $query->where('is_banned', true);
            } elseif ($request->status === 'active') {
                $query->where('is_banned', false);
            }
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Get specific user details.
     */
    public function show(User $user)
    {
        try {
            $user->load(['plan', 'subscriptions.plan', 'logs' => fn($q) => $q->latest()->take(20)]);
            $user->loadCount(['simulations', 'essays', 'promptLogs']);

            $stats = [
                'simulations' => $user->simulations_count,
                'essays' => $user->essays_count,
                'ai' => [
                    'request_count' => $user->prompt_logs_count,
                    'total_cost' => $user->promptLogs()->sum('estimated_cost') ?? 0,
                    'success_rate' => $user->prompt_logs_count > 0
                        ? ($user->promptLogs()->whereNotNull('response_text')->count() / $user->prompt_logs_count) * 100
                        : 100,
                    'peak_hour' => 'N/A',
                ],
                'investment' => $user->subscriptions()->where('status', 'active')->get()->sum(fn($s) => $s->plan?->price ?? 0)
            ];

            return response()->json([
                'user' => $user,
                'stats' => $stats,
                'promptHistory' => $user->promptLogs()->latest()->paginate(10),
                'monthly_simulation_used' => $user->monthlySimulationUsed(),
                'monthly_essay_used' => $user->monthlyEssayUsed(),
            ]);
        } catch (\Exception $e) {
            \Log::error("Error loading user detail (ID: {$user->id}): " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal Server Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle ban status.
     */
    public function toggleStatus(User $user)
    {
        $user->update(['is_banned' => !$user->is_banned]);
        return response()->json(['message' => 'Status alterado!', 'user' => $user]);
    }

    /**
     * Reset password logic (simplified for API).
     */
    public function resetPassword(Request $request, User $user)
    {
        if ($request->filled('new_password')) {
            $user->update(['password' => bcrypt($request->new_password)]);
        }

        // In a real app, send email if requested

        return response()->json(['message' => 'Operação concluída.']);
    }

    /**
     * Update user role or information (Admin only).
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string',
            'role' => 'nullable|in:user,admin,editor',
            'ai_questions_count' => 'nullable|integer|min:0',
            'max_ai_questions_override' => 'nullable|integer|min:0',
            'max_simulations_override' => 'nullable|integer|min:0',
            'max_essays_override' => 'nullable|integer|min:0',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuário atualizado com sucesso!',
            'user' => $user->load('plan')
        ]);
    }
}
