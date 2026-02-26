<?php

use App\Http\Controllers\Api\ConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Prefixo: /api/v1
|--------------------------------------------------------------------------
|
| Rotas da API REST consumida pelo Frontend React SPA.
| Autenticação via Laravel Sanctum (cookie-based SPA).
|
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // Público — Não requer autenticação
    // ---------------------------------------------------------------

    // Bootstrap: configurações iniciais do sistema (branding, planos, features)
    Route::get('/config', [ConfigController::class, 'index'])->name('api.config');

    // Autenticação Pública
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login'])->name('api.login');
    Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register'])->name('api.register');

    // ---------------------------------------------------------------
    // Autenticado — Rotas protegidas por Sanctum SPA
    // ---------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::get('/user', [\App\Http\Controllers\Api\AuthController::class, 'user'])->name('api.user');
        Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->name('api.logout');

        // Dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index'])->name('api.dashboard');

        // Simulations
        Route::apiResource('simulations', \App\Http\Controllers\Api\SimulationController::class)->only(['index', 'show', 'store']);
        Route::get('simulations/{simulation}/status', [\App\Http\Controllers\Api\SimulationController::class, 'status'])->name('api.simulations.status');
        Route::post('simulations/{simulation}/answer', [\App\Http\Controllers\Api\SimulationController::class, 'answer'])->name('api.simulations.answer');
        Route::post('simulations/{simulation}/finish', [\App\Http\Controllers\Api\SimulationController::class, 'finish'])->name('api.simulations.finish');
        Route::post('simulations/{simulation}/submit', [\App\Http\Controllers\Api\SimulationController::class, 'submit'])->name('api.simulations.submit');

        // Study Plans
        Route::get('study-plan', [\App\Http\Controllers\Api\StudyPlanController::class, 'index'])->name('api.study-plan.index');
        Route::post('study-plan', [\App\Http\Controllers\Api\StudyPlanController::class, 'store'])->name('api.study-plan.store');
        Route::get('study-plan/status', [\App\Http\Controllers\Api\StudyPlanController::class, 'status'])->name('api.study-plan.status');
        Route::post('study-plan/update', [\App\Http\Controllers\Api\StudyPlanController::class, 'update'])->name('api.study-plan.update');

        // Concursos
        Route::get('concursos', [\App\Http\Controllers\Api\ConcursoController::class, 'index'])->name('api.concursos.index');

        // Essays
        Route::apiResource('essays', \App\Http\Controllers\Api\EssayController::class)->only(['index', 'show', 'store']);

        // Subscriptions & Checkout
        Route::prefix('plans')->group(function () {
            Route::post('/{plan}/validate-coupon', [\App\Http\Controllers\Api\SubscriptionController::class, 'validateCoupon'])->name('api.plans.validate-coupon');
            Route::post('/{plan}/checkout', [\App\Http\Controllers\Api\SubscriptionController::class, 'store'])->name('api.plans.checkout');
            Route::get('/check-status', [\App\Http\Controllers\Api\SubscriptionController::class, 'checkStatus'])->name('api.plans.check-status');
        });

        // Question Bank
        Route::prefix('questions')->group(function () {
            Route::get('/subjects', [\App\Http\Controllers\Api\QuestionController::class, 'subjects'])->name('api.questions.subjects');
            Route::get('/topics', [\App\Http\Controllers\Api\QuestionController::class, 'topics'])->name('api.questions.topics');
            Route::get('/stats', [\App\Http\Controllers\Api\QuestionController::class, 'stats'])->name('api.questions.stats');
            Route::get('/{question}/history', [\App\Http\Controllers\Api\QuestionController::class, 'history'])->name('api.questions.history');
            Route::post('/{question}/answer', [\App\Http\Controllers\Api\QuestionController::class, 'answer'])->name('api.questions.answer');

            // Xavier AI Search
            Route::post('/ai-search', [\App\Http\Controllers\AiSearchController::class, 'search'])->name('api.ai-search');
            Route::get('/ai-search/{searchRequest}/status', [\App\Http\Controllers\AiSearchController::class, 'status'])->name('api.ai-search.status');

            // Chat Standalone (Tirar Dúvida)
            Route::post('/{question}/chat', [\App\Http\Controllers\QuestionChatController::class, 'storeStandalone'])->name('api.chat.store');
            Route::post('/{question}/chat/stream', [\App\Http\Controllers\QuestionChatController::class, 'streamStandalone'])->name('api.chat.stream');
            Route::get('/{question}/chat', [\App\Http\Controllers\QuestionChatController::class, 'indexStandalone'])->name('api.chat.index');
        });
        // Profile
        Route::prefix('user')->group(function () {
            Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update'])->name('api.profile.update');
            Route::put('/password', [\App\Http\Controllers\Api\ProfileController::class, 'updatePassword'])->name('api.profile.password.update');
        });

        // Admin (Protected by Sanctum + Role Check in Controller or Middleware)
        Route::prefix('admin')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\Api\AdminController::class, 'dashboard'])->name('api.admin.dashboard');
            Route::get('/curadoria', [\App\Http\Controllers\Api\Admin\CuradoriaController::class, 'index'])->name('api.admin.curadoria.index');

            // Administrative CRUDs
            Route::post('questions/{question}/evaluate-difficulty', [\App\Http\Controllers\Api\Admin\QuestionController::class, 'evaluateDifficulty'])->name('api.admin.questions.evaluate-difficulty');
            Route::post('questions/{question}/generate-explanation', [\App\Http\Controllers\Api\Admin\QuestionController::class, 'generateExplanation'])->name('api.admin.questions.generate-explanation');
            Route::post('questions/{question}/complete', [\App\Http\Controllers\Api\Admin\QuestionController::class, 'completeQuestion'])->name('api.admin.questions.complete');
            Route::post('questions/{question}/classify', [\App\Http\Controllers\Api\Admin\QuestionController::class, 'classifyQuestion'])->name('api.admin.questions.classify');
            Route::apiResource('questions', \App\Http\Controllers\Api\Admin\QuestionController::class)->names('api.admin.questions');
            Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class)->names('api.admin.users');

            // Infrastructure & AI Monitoring
            Route::get('/api-keys', [\App\Http\Controllers\Api\Admin\ApiKeyController::class, 'index'])->name('api.admin.api-keys.index');
            Route::post('/api-keys/vault', [\App\Http\Controllers\Api\Admin\ApiKeyController::class, 'storeVault'])->name('api.admin.api-keys.vault.store');
            Route::post('/api-keys/{apiKey}/toggle', [\App\Http\Controllers\Api\Admin\ApiKeyController::class, 'toggle'])->name('api.admin.api-keys.toggle');

            // AI Batch Triage
            Route::prefix('triage')->group(function () {
                Route::post('/preview', [\App\Http\Controllers\Api\Admin\AIBatchTriageController::class, 'preview'])->name('api.admin.triage.preview');
                Route::post('/start', [\App\Http\Controllers\Api\Admin\AIBatchTriageController::class, 'start'])->name('api.admin.triage.start');
                Route::get('/{batchId}/status', [\App\Http\Controllers\Api\Admin\AIBatchTriageController::class, 'status'])->name('api.admin.triage.status');
                Route::post('/{batchId}/cancel', [\App\Http\Controllers\Api\Admin\AIBatchTriageController::class, 'cancel'])->name('api.admin.triage.cancel');
            });
        });
    });
});
