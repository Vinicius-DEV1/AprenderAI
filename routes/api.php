<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\SimulationController;
use App\Http\Controllers\Api\StudyPlanController;
use App\Http\Controllers\Api\ConcursoController;
use App\Http\Controllers\Api\EssayController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\QuestionChatController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Admin\CuradoriaController;
use App\Http\Controllers\Api\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\SystemPromptController as AdminSystemPromptController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\ApiKeyController as AdminApiKeyController;
use App\Http\Controllers\Api\Admin\AIBatchTriageController as AdminAIBatchTriageController;

/*
|--------------------------------------------------------------------------
| API Routes — Prefixo: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.')->group(function () {

    // Público
    Route::get('/config', [ConfigController::class, 'index'])->name('api.config');
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/register', [AuthController::class, 'register'])->name('api.register');

    // Autenticado
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('api.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

        // Resources
        Route::apiResource('simulations', SimulationController::class)->only(['index', 'show', 'store']);
        Route::get('simulations/{simulation}/status', [SimulationController::class, 'status']);
        Route::post('simulations/{simulation}/answer', [SimulationController::class, 'answer']);
        Route::post('simulations/{simulation}/finish', [SimulationController::class, 'finish']);
        Route::post('simulations/{simulation}/submit', [SimulationController::class, 'submit']);

        Route::get('study-plan', [StudyPlanController::class, 'index']);
        Route::post('study-plan', [StudyPlanController::class, 'store']);
        Route::get('study-plan/status', [StudyPlanController::class, 'status']);
        Route::post('study-plan/update', [StudyPlanController::class, 'update']);

        Route::get('concursos', [ConcursoController::class, 'index']);
        Route::apiResource('essays', EssayController::class)->only(['index', 'show', 'store']);

        // Plans & Subscriptions
        Route::prefix('plans')->group(function () {
            Route::post('/{plan}/validate-coupon', [SubscriptionController::class, 'validateCoupon']);
            Route::post('/{plan}/checkout', [SubscriptionController::class, 'store']);
            Route::get('/check-status', [SubscriptionController::class, 'checkStatus']);
        });

        // Question Bank
        Route::prefix('questions')->group(function () {
            Route::get('/', [QuestionController::class, 'index']);
            Route::get('/subjects', [QuestionController::class, 'subjects']);
            Route::get('/topics', [QuestionController::class, 'topics']);
            Route::get('/stats', [QuestionController::class, 'stats']);
            Route::get('/{question}/history', [QuestionController::class, 'history']);
            Route::post('/{question}/answer', [QuestionController::class, 'answer']);
            Route::post('/ai-search', [AiSearchController::class, 'search']);
            Route::get('/ai-search/{searchRequest}/status', [AiSearchController::class, 'status']);
            Route::post('/{question}/chat', [QuestionChatController::class, 'storeStandalone']);
            Route::post('/{question}/chat/stream', [QuestionChatController::class, 'streamStandalone']);
            Route::get('/{question}/chat', [QuestionChatController::class, 'indexStandalone']);
        });

        // Profile
        Route::prefix('user')->group(function () {
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'updatePassword']);
        });

        // Admin
        Route::prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/curadoria', [CuradoriaController::class, 'index']);

            // Administrative CRUDs
            Route::get('questions/support-data', [AdminQuestionController::class, 'supportData']);
            Route::apiResource('questions', AdminQuestionController::class);
            Route::post('questions/{question}/evaluate-difficulty', [AdminQuestionController::class, 'evaluateDifficulty']);
            Route::post('questions/{question}/generate-explanation', [AdminQuestionController::class, 'generateExplanation']);
            Route::post('questions/{question}/complete', [AdminQuestionController::class, 'completeQuestion']);
            Route::post('questions/{question}/classify', [AdminQuestionController::class, 'classifyQuestion']);

            Route::apiResource('users', AdminUserController::class);
            Route::apiResource('plans', AdminPlanController::class);
            Route::apiResource('coupons', AdminCouponController::class);
            Route::apiResource('prompts', AdminSystemPromptController::class);

            // Settings & Cache
            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::post('/settings', [AdminSettingController::class, 'update']);
            Route::post('/settings/clear-cache', [AdminSettingController::class, 'clearCache']);

            // Infrastructure & AI Monitoring
            Route::get('/api-keys', [AdminApiKeyController::class, 'index']);
            Route::post('/api-keys/vault', [AdminApiKeyController::class, 'storeVault']);
            Route::post('/api-keys/{apiKey}/toggle', [AdminApiKeyController::class, 'toggle']);
            Route::post('/api-keys/priority', [AdminApiKeyController::class, 'updatePriority']);
            Route::post('/api-keys/discover', [AdminApiKeyController::class, 'discoverModels']);
            Route::post('/api-keys/{apiKey}/retest', [AdminApiKeyController::class, 'retest']);

            // AI Batch Triage
            Route::prefix('triage')->group(function () {
                Route::post('/preview', [AdminAIBatchTriageController::class, 'preview']);
                Route::post('/start', [AdminAIBatchTriageController::class, 'start']);
                Route::get('/{batchId}/status', [AdminAIBatchTriageController::class, 'status']);
                Route::post('/{batchId}/cancel', [AdminAIBatchTriageController::class, 'cancel']);
            });
        });
    });
});
