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

use Illuminate\Support\Facades\Password;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Auth\Events\Verified;

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
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            \App\Models\UserLog::create([
                'user_id' => Auth::id(),
                'action' => 'login',
                'ip_address' => $request->ip(),
                'description' => 'Login via e-mail e senha efetuado com sucesso.',
            ]);

            // Retorna o usuário logado para o frontend decidir o redirecionamento (admin vs user)
            return response()->json([
                'user' => Auth::user()->load('plan')
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

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => $request->ip(),
            'description' => 'Cadastro e primeiro login efetuado com sucesso.',
        ]);

        // Em SPA, guardamos a sessão, redirecionamentos complexos como select_plan 
        // ficam a cargo do frontend no momento adequado
        return response()->json([
            'user' => $user->load('plan')
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout relizado com sucesso']);
    }

    public function user(Request $request)
    {
        $user = $request->user()->loadMissing(['plan', 'stats']);
        return response()->json([
            'user' => (new \App\Http\Resources\UserResource($user))->resolve()
        ]);
    }

    // =========================================================================
    // VERIFICAÇÃO DE EMAIL E RECUPERAÇÃO DE SENHA
    // =========================================================================

    public function verifyEmail($id, $hash, Request $request)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Link inválido ou expirado.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5174') . '/dashboard?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect(env('FRONTEND_URL', 'http://localhost:5174') . '/dashboard?verified=1');
    }

    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Seu e-mail já está verificado.'], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['status' => 'Um novo link de verificação foi enviado para o seu e-mail.']);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => 'already_verified'], 200);
        }

        try {
            $user->notify(new \App\Notifications\QueuedVerifyEmail);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao enviar e-mail assíncrono de verificação: ' . $e->getMessage(), [
                'user_id' => $user->id
            ]);
            // Ocultar a falha de envio do front-end. Retornamos 'success' para que o fluxo do front-end não quebre.
        }

        return response()->json(['status' => 'success'], 200);
    }

    public function forgotPasswordProxy(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Regra de Negócio: Impede recuperação se o usuário existe, mas o e-mail não está configurado.
        if ($user && is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'E-mail não confirmado.',
                'errors' => ['email' => ['Você não confirmou o seu e-mail no momento do cadastro. Por segurança, a redefinição de senha está indisponível para esta conta.']]
            ], 403);
        }

        // Se o email passou pela checagem (ou não existe no BD, enviando e-mail falso), repassamos para a regra padrão.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['status' => __($status)])
            : response()->json(['errors' => ['email' => [__($status)]]], 422);
    }
}
