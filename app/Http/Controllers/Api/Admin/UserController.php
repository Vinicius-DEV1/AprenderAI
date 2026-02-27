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
        // Optimization: Use loadCount to fetch all totals in a single query
        $user->loadCount(['simulations', 'essays', 'promptLogs']);
        $user->load(['plan', 'subscriptions.plan']);

        $stats = [
            'simulations' => $user->simulations_count,
            'essays' => $user->essays_count,
            'ai' => [
                'request_count' => $user->prompt_logs_count,
                'total_cost' => $user->promptLogs()->sum('estimated_cost'),
                'success_rate' => $user->promptLogs()->where('status', 'success')->count() / max(1, $user->prompt_logs_count) * 100,
                'peak_hour' => 'N/A',
            ]
        ];

        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'monthly_simulation_used' => $user->simulations()->whereMonth('created_at', now()->month)->count(),
            'monthly_essay_used' => $user->essays()->whereMonth('created_at', now()->month)->count(),
        ]);
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
            'role' => 'nullable|in:user,admin,editor',
            'is_active' => 'boolean',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuário atualizado com sucesso!',
            'user' => $user
        ]);
    }
}
