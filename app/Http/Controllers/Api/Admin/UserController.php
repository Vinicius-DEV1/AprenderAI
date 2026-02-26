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
        $query = User::with(['plan']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Get specific user details.
     */
    public function show(User $user)
    {
        $user->load(['plan', 'subscriptions']);
        return response()->json($user);
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
