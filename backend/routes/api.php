<?php

use Illuminate\Http\Request;
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
use App\Http\Controllers\Api\Admin\EnemImportController as AdminEnemImportController;
use App\Http\Controllers\Api\Admin\AdminEssayController;
use App\Http\Controllers\Api\Admin\AdminQuestionImportController;
use App\Http\Controllers\Api\Admin\AdminImportReviewController;

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

    // Verificacao via URL enviada por Email
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Proxy para forgot-password que testa confirmacao
    Route::post('/forgot-password', [AuthController::class, 'forgotPasswordProxy'])->name('api.forgot-password');

    // Autenticado
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('api.user');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

        // Email Verification
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
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
        Route::post('essays/{essay}/start-topic', [EssayController::class, 'startTopicGeneration']);
        Route::get('essays/{essay}/topic-status', [EssayController::class, 'getTopicStatus']);
        Route::post('essays/{essay}/submit', [EssayController::class, 'submit']);
        Route::post('essays/{essay}/retry', [EssayController::class, 'retryEvaluation']);

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
        Route::prefix('admin')->middleware(['is.admin'])->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/curadoria', [CuradoriaController::class, 'index']);

            // Administrative CRUDs
            Route::get('questions/support-data', [AdminQuestionController::class, 'supportData']);
            Route::apiResource('questions', AdminQuestionController::class);
            Route::post('questions/{question}/evaluate-difficulty', [AdminQuestionController::class, 'evaluateDifficulty']);
            Route::post('questions/{question}/generate-explanation', [AdminQuestionController::class, 'generateExplanation']);
            Route::post('questions/{question}/complete', [AdminQuestionController::class, 'completeQuestion']);
            Route::post('questions/{question}/classify', [AdminQuestionController::class, 'classifyQuestion']);
            Route::post('questions/{question}/retry-evaluation', [AdminQuestionController::class, 'completeQuestion']); // Re-use completeQuestion for full retry

            Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::apiResource('users', AdminUserController::class);
            Route::apiResource('plans', AdminPlanController::class);
            Route::apiResource('coupons', AdminCouponController::class);
            Route::apiResource('prompts', AdminSystemPromptController::class);

            // Essays Admin
            Route::get('essays', [AdminEssayController::class, 'index']);
            Route::post('essays/{essay}/retry', [AdminEssayController::class, 'retry']);

            // API Pricing
            Route::get('/api-pricing', [AdminApiPricingController::class, 'index']);
            Route::put('/api-pricing/{apiPricing}', [AdminApiPricingController::class, 'update']);
            Route::get('/api-pricing/{apiPricing}/logs', [AdminApiPricingController::class, 'logs']);

            // Settings & Cache
            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::post('/settings', [AdminSettingController::class, 'update']);
            Route::post('/settings/clear-cache', [AdminSettingController::class, 'clearCache']);

            // Infrastructure & AI Monitoring
            Route::get('/api-keys', [AdminApiKeyController::class, 'index']);
            Route::post('/api-keys', [AdminApiKeyController::class, 'store']);
            Route::post('/api-keys/vault', [AdminApiKeyController::class, 'storeVault']);
            Route::post('/api-keys/{apiKey}/toggle', [AdminApiKeyController::class, 'toggle']);
            Route::post('/api-keys/priority', [AdminApiKeyController::class, 'updatePriority']);
            Route::post('/api-keys/discover', [AdminApiKeyController::class, 'discoverModels']);
            Route::post('/api-keys/{apiKey}/retest', [AdminApiKeyController::class, 'retest']);
            Route::delete('/api-keys/{id}', [AdminApiKeyController::class, 'destroy']);

            // AI Batch Triage
            Route::prefix('triage')->group(function () {
                Route::post('/preview', [AdminAIBatchTriageController::class, 'preview']);
                Route::post('/start', [AdminAIBatchTriageController::class, 'start']);
                Route::get('/{batchId}/status', [AdminAIBatchTriageController::class, 'status']);
                Route::post('/{batchId}/cancel', [AdminAIBatchTriageController::class, 'cancel']);
            });

            // ENEM Import
            Route::get('/enem', [AdminEnemImportController::class, 'index']);
            Route::post('/enem', [AdminEnemImportController::class, 'store']);
            Route::get('/enem/status', [AdminEnemImportController::class, 'status']);

            // Import Review
            Route::prefix('import/review')->group(function () {
                Route::get('/', [AdminImportReviewController::class, 'index']);
                Route::get('/{id}', [AdminImportReviewController::class, 'show']);
                Route::post('/{id}/approve', [AdminImportReviewController::class, 'approve']);
                Route::post('/{id}/revert', [AdminImportReviewController::class, 'revert']);
                Route::post('/{imageId}/crop', [AdminImportReviewController::class, 'crop']);
                Route::delete('/{imageId}/image', [AdminImportReviewController::class, 'deleteImage']);
            });

            // Admin Question Import
            Route::prefix('import')->group(function () {
                Route::get('/', [AdminQuestionImportController::class, 'index']);
                Route::post('/', [AdminQuestionImportController::class, 'store']);
                Route::get('/active-job', [AdminQuestionImportController::class, 'activeJob']);
                Route::get('/{id}/progress', [AdminQuestionImportController::class, 'progress']);
            });
        });
    });
});
