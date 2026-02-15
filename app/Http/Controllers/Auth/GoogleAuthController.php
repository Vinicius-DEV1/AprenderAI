<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
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
            return redirect()->route('login')->with('error', 'Google Login is disabled.');
        }

        $this->configureGoogle();

        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        if (!Configuration::get('google_login_enabled', false)) {
            return redirect()->route('login')->with('error', 'Google Login is disabled.');
        }

        $this->configureGoogle();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Failed to authenticate with Google.');
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
                'password' => bcrypt(str()->random(16)), // Random password
                'email_verified_at' => now(),
            ]);
        }

        // Log in the user
        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
