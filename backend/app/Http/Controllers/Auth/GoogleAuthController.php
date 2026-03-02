<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;

use App\Services\PlanService;

class GoogleAuthController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    private function configureGoogle()
    {
        $clientId = Configuration::get('google_client_id');
        $clientSecretEncrypted = Configuration::get('google_client_secret');
        $redirectUri = Configuration::get('google_redirect_uri');

        if (!$clientId || !$clientSecretEncrypted || !$redirectUri) {
            abort(500, 'Google Login not configured.');
        }

        try {
            $clientSecret = Crypt::decryptString($clientSecretEncrypted);
        } catch (\Exception $e) {
            abort(500, 'Invalid Google Client Secret configuration.');
        }

        Config::set('services.google.client_id', $clientId);
        Config::set('services.google.client_secret', $clientSecret);
        Config::set('services.google.redirect', $redirectUri);
    }

    public function redirect()
    {
        if (!Configuration::get('google_login_enabled', false)) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5174') . '/login?error=google_disabled');
        }

        $this->configureGoogle();

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5174');

        if (!Configuration::get('google_login_enabled', false)) {
            return redirect($frontendUrl . '/login?error=google_disabled');
        }

        $this->configureGoogle();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/login?error=google_failed');
        }

        // 1. Try to find user by google_id
        $user = User::where('google_id', $googleUser->getId())->first();

        // 2. If not found, try to find by email
        if (!$user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Link Google ID to existing user
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ]);
            }
        }

        // 3. If still not found, create new user
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'password' => bcrypt(str()->random(16)),
                'email_verified_at' => now(),
            ]);

            // Assign free plan by default
            try {
                $freePlan = $this->planService->getFreePlan();
                $this->planService->assignPlanToUser($user, $freePlan);
            } catch (\Exception $e) {
                logger()->error('Failed to assign free plan to new Google user: ' . $e->getMessage());
            }
        }

        // 4. Ensure user has a plan (defensive fix for accounts created without one)
        if (!$user->plan_id) {
            try {
                $freePlan = $this->planService->getFreePlan();
                $this->planService->assignPlanToUser($user, $freePlan);
            } catch (\Exception $e) {
                logger()->error('Failed to assign free plan to user on login: ' . $e->getMessage());
            }
        }

        // Log in the user
        Auth::login($user);

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => request()->ip(),
            'description' => 'Login via Google SSO efetuado com sucesso.',
        ]);

        // CRO: Redirect new or free users to onboarding (only once per login)
        if ((!$user->plan || $user->plan->slug === 'free') && !session('onboarding_shown')) {
            session(['onboarding_shown' => true]);
            return redirect($frontendUrl . '/bem-vindo');
        }

        return redirect($frontendUrl . '/dashboard');
    }
}
