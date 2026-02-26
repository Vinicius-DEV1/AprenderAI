<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Retorna o usuário logado para o frontend decidir o redirecionamento (admin vs user)
            return response()->json([
                'user' => Auth::user()
            ]);
        }

        return response()->json([
            'message' => 'As credenciais fornecidas não correspondem aos nossos registros.',
            'errors' => [
                'email' => ['As credenciais fornecidas não correspondem aos nossos registros.']
            ]
        ], 422);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Atribuir plano gratuito automaticamente
        $freePlan = $this->planService->getFreePlan();
        if ($freePlan) {
            $this->planService->assignPlanToUser($user, $freePlan);
        }

        event(new Registered($user));

        Auth::login($user);

        // Em SPA, guardamos a sessão, redirecionamentos complexos como select_plan 
        // ficam a cargo do frontend no momento adequado
        return response()->json([
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout relizado com sucesso']);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }
}
