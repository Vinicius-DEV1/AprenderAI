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
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_banned', false);
            } elseif ($request->status === 'banned') {
                $query->where('is_banned', true);
            }
        }

        $users = $query->with('plan')->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['subscriptions', 'logs' => fn($q) => $q->latest()->take(20)]);
        
        // Mocking some stats if not available in a relation
        $stats = [
            'simulations' => \App\Models\Simulation::where('user_id', $user->id)->count(),
            'essays' => \App\Models\Essay::where('user_id', $user->id)->count(),
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ]);

        $original = $user->getOriginal();
        $user->update($request->only('name', 'email', 'phone'));

        // Description of changes
        $changes = [];
        if ($original['name'] !== $user->name) $changes[] = "Nome: {$original['name']} -> {$user->name}";
        if ($original['email'] !== $user->email) $changes[] = "Email: {$original['email']} -> {$user->email}";
        if ($original['phone'] !== $user->phone) $changes[] = "Telefone: {$original['phone']} -> {$user->phone}";

        if (!empty($changes)) {
            UserLog::create([
                'user_id' => $user->id,
                'action' => 'admin_update',
                'description' => 'Perfil atualizado por Admin. ' . implode(', ', $changes),
                'ip_address' => $request->ip(),
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
