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

        // Dynamically generate the redirect URI based on current server request.
        // This ensures mismatch errors (http/https, domain) are avoided if the DB has stale data.
        $redirectUri = url('/auth/google/callback');

        if (!$clientId || !$clientSecretEncrypted) {
            abort(500, 'Google Login not configured (missing Client ID or Secret).');
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
        $enabled = Configuration::get('google_login_enabled', false);
        // Robust boolean check for DB values (might be '1', '0', 1, 0, or true/false)
        if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return redirect(env('FRONTEND_URL', 'http://localhost:5174') . '/login?error=google_disabled');
        }

        $this->configureGoogle();

        if (request()->has('plan')) {
            session(['intended_plan' => request('plan')]);
        }

        // Persist UTM parameters to session for use after Google callback
        $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmParams as $param) {
            if (request()->has($param)) {
                session([$param => request($param)]);
            }
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5174'), '/');
        $enabled = Configuration::get('google_login_enabled', false);

        if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return redirect($frontendUrl . '/login?error=google_disabled');
        }

        $this->configureGoogle();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/login?error=google_failed');
        }

        $isNewUser = false;

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
            $isNewUser = true;

            // Retrieve and clear UTM parameters from session
            $utmData = [];
            $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
            foreach ($utmParams as $param) {
                $utmData[$param] = session()->pull($param, null);
            }

            $user = User::create(array_merge([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'password' => bcrypt(str()->random(16)),
                'email_verified_at' => now(),
            ], $utmData));

            // Assign free plan by default
            try {
                $freePlan = $this->planService->getFreePlan();
                $this->planService->assignPlanToUser($user, $freePlan);
            } catch (\Exception $e) {
                logger()->error('Failed to assign free plan to new Google user: ' . $e->getMessage());
            }

            // Criar registro de estatísticas inicial
            \App\Models\UserStat::create([
                'user_id' => $user->id,
            ]);
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

        // Regenerate the session after login to prevent session fixation attacks
        // and to ensure the SPA picks up a clean, valid session cookie on its
        // very first request after the Google redirect.
        request()->session()->regenerate();

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => request()->ip(),
            'description' => 'Login via Google SSO efetuado com sucesso.',
        ]);

        // CRO: Redirect exclusively new users to onboarding
        if ($isNewUser) {
            $plan = session()->pull('intended_plan');
            $redirectUrl = $frontendUrl . '/welcome';
            if ($plan) {
                $redirectUrl .= '?plan=' . urlencode($plan);
            }
            return redirect($redirectUrl);
        }

        return redirect($frontendUrl . '/dashboard');
    }
}
