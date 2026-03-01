<?php

use Illuminate\Support\Facades\Route;

// -------------------------------------------------------
// ROOT — Serve the React SPA
// -------------------------------------------------------
Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

// -------------------------------------------------------
// Public utility routes (non-SPA, non-API)
// -------------------------------------------------------
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

// -------------------------------------------------------
// Webhooks (bypass CSRF — already excluded in bootstrap/app.php)
// -------------------------------------------------------
Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookController::class, 'handleAsaas'])->name('webhooks.asaas');

// -------------------------------------------------------
// Email Verification Helper (Native Laravel requires 'verification.verify' exact name)
// Defined here to escape the `api.` route prefix from the API group.
// -------------------------------------------------------
Route::get('/api/v1/email/verify/{id}/{hash}', [\App\Http\Controllers\Api\AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// -------------------------------------------------------
// Google OAuth — server-side redirect flow
// -------------------------------------------------------
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// -------------------------------------------------------
// Fallback — API routes not found return JSON; everything
// else falls back to the React SPA (client-side routing).
// -------------------------------------------------------
Route::fallback(function () {
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Not Found'], 404);
    }

    return response()->file(public_path('index.html'));
});
