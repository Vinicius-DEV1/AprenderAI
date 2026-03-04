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
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\NotebookController;
use App\Http\Controllers\Api\QuestionNoteController;
use App\Http\Controllers\Api\QuestionReportController;
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
use App\Http\Controllers\Api\Admin\AdminSimulationController;
use App\Http\Controllers\Api\Admin\ApiPricingController;
use App\Http\Controllers\Api\Admin\PaymentSettingsController;
use App\Http\Controllers\Api\Admin\ExamController;

/*
|--------------------------------------------------------------------------
| API Routes — Prefixo: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Webhooks
    Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookController::class, 'handleAsaas'])->name('api.webhooks.asaas');

    // Público
    Route::get('/config', [ConfigController::class, 'index'])->name('api.config');
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/register', [AuthController::class, 'register'])->name('api.register');

    // Verificacao via URL enviada por Email (agora com prefixo api. automatico)
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
        Route::post('/email/resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:3,1')
            ->name('verification.resend.async');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

        // Resources
        Route::apiResource('simulations', SimulationController::class)->only(['index', 'show', 'store']);
        Route::get('simulations/{simulation}/status', [SimulationController::class, 'status']);
        Route::post('simulations/{simulation}/answer', [SimulationController::class, 'answer']);
        Route::post('simulations/{simulation}/finish', [SimulationController::class, 'finish']);
        Route::post('simulations/{simulation}/submit', [SimulationController::class, 'submit']);
        Route::prefix('simulations/{simulation}/questions/{question}')->group(function () {
            Route::get('chat', [SimulationController::class, 'chat'])->name('simulations.questions.chat.index');
            Route::post('chat', [SimulationController::class, 'sendChat'])->name('simulations.questions.chat.store');
        });

        Route::get('study-plan', [StudyPlanController::class, 'index']);
        Route::post('study-plan', [StudyPlanController::class, 'store']);
        Route::get('study-plan/status', [StudyPlanController::class, 'status']);
        Route::post('study-plan/update', [StudyPlanController::class, 'update']);

        Route::get('concursos', [ConcursoController::class, 'index']);
        Route::get('essays/rule/{type}', [EssayController::class, 'getRule']); // Must be before apiResource
        Route::apiResource('essays', EssayController::class)->only(['index', 'show', 'store', 'update']);
        Route::post('essays/{essay}/start-topic', [EssayController::class, 'startTopicGeneration']);
        Route::get('essays/{essay}/topic-status', [EssayController::class, 'getTopicStatus']);
        Route::post('essays/{essay}/submit', [EssayController::class, 'submit']);
        Route::post('essays/{essay}/retry', [EssayController::class, 'retryEvaluation']);

        // Subscriptions
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index']);
            Route::post('/{plan}/validate-coupon', [SubscriptionController::class, 'validateCoupon']);
            Route::post('/{plan}/checkout', [SubscriptionController::class, 'store']);
            Route::get('/check-status', [SubscriptionController::class, 'checkStatus']);
            Route::get('/{subscription}/receipt', [SubscriptionController::class, 'receiptUrl']);
        });

        // Notebooks
        Route::apiResource('notebooks', NotebookController::class);
        Route::post('notebooks/{notebook}/questions/{question}', [NotebookController::class, 'addQuestion']);
        Route::delete('notebooks/{notebook}/questions/{question}', [NotebookController::class, 'removeQuestion']);
        Route::post('questions/{question}/sync-notebooks', [NotebookController::class, 'syncQuestion']);

        // Question Bank
        Route::prefix('questions')->group(function () {
            Route::get('/essay-themes', [QuestionController::class, 'essayThemes']);
            Route::get('/', [QuestionController::class, 'index']);
            Route::get('/subjects', [QuestionController::class, 'subjects']);
            Route::get('/topics', [QuestionController::class, 'topics']);
            Route::get('/filter-options', [QuestionController::class, 'filterOptions']);
            Route::get('/stats', [QuestionController::class, 'stats']);
            Route::get('/{question}/history', [QuestionController::class, 'history']);
            Route::post('/{question}/view', [QuestionController::class, 'logView']);
            Route::post('/{question}/answer', [QuestionController::class, 'answer'])
                ->middleware('check.plan.limits:daily_question');

            // Notes, Favorites, Reports, and Stats
            Route::post('/{question}/favorite', [FavoriteController::class, 'toggle']);
            Route::post('/{question}/report', [QuestionReportController::class, 'store']);
            Route::get('/{question}/stats', [QuestionController::class, 'questionStats']);
            Route::get('/{question}/notes', [QuestionNoteController::class, 'index']);
            Route::post('/{question}/notes', [QuestionNoteController::class, 'store']);
            Route::put('/notes/{note}', [QuestionNoteController::class, 'update']);
            Route::delete('/notes/{note}', [QuestionNoteController::class, 'destroy']);

            // Xavier AI Search
            Route::post('/ai-search', [QuestionController::class, 'aiSearch'])->name('questions.ai-search');
            Route::get('/ai-search/{aiSearchRequest}/status', [QuestionController::class, 'aiSearchStatus'])->name('questions.ai-search.status');

            // Xavier Chat
            Route::get('/{question}/chat', [QuestionController::class, 'chat'])->name('questions.chat.index');
            Route::post('/{question}/chat', [QuestionController::class, 'sendChat'])->name('questions.chat.store');

            // Engagement & Goals
            Route::get('/engagement', [QuestionController::class, 'engagement']);
            Route::post('/goal', [QuestionController::class, 'updateGoal']);
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

            Route::get('question-reports', [QuestionReportController::class, 'index']);
            Route::post('question-reports/{report}/resolve', [QuestionReportController::class, 'resolve']);
            Route::post('questions/{question}/deactivate', [QuestionReportController::class, 'deactivateQuestion']);

            // Exams (Provas) Visualization
            Route::get('exams', [ExamController::class, 'index']);
            Route::get('exams/{id}', [ExamController::class, 'show']);

            // Administrative CRUDs
            Route::get('questions/support-data', [AdminQuestionController::class, 'supportData']);
            Route::get('questions/trashed', [AdminQuestionController::class, 'trashed']);
            Route::post('questions/{id}/restore', [AdminQuestionController::class, 'restore']);
            Route::delete('questions/{id}/force', [AdminQuestionController::class, 'forceDelete']);

            Route::get('questions/{question}/delete-impact', [AdminQuestionController::class, 'deleteImpact']);
            Route::apiResource('questions', AdminQuestionController::class);
            Route::post('questions/{question}/evaluate-difficulty', [AdminQuestionController::class, 'evaluateDifficulty']);
            Route::post('questions/{question}/generate-explanation', [AdminQuestionController::class, 'generateExplanation']);
            Route::post('questions/{question}/complete', [AdminQuestionController::class, 'completeQuestion']);
            Route::post('questions/{question}/classify', [AdminQuestionController::class, 'classifyQuestion']);
            Route::post('questions/{question}/retry-evaluation', [AdminQuestionController::class, 'completeQuestion']); // Re-use completeQuestion for full retry

            Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('users/{user}/refund', [AdminUserController::class, 'refundAndCancel']);
            Route::get('users/{user}/stats', [AdminUserController::class, 'stats']);
            Route::apiResource('users', AdminUserController::class);
            Route::apiResource('plans', AdminPlanController::class);
            Route::apiResource('coupons', AdminCouponController::class);
            Route::apiResource('prompts', AdminSystemPromptController::class);

            // Essays Admin
            Route::get('essays', [AdminEssayController::class, 'index']);
            Route::post('essays/{essay}/retry', [AdminEssayController::class, 'retry']);

            // API Pricing
            Route::get('/api-pricing', [ApiPricingController::class, 'index']);
            Route::get('/api-pricing/vaults', [ApiPricingController::class, 'vaults']);
            Route::post('/api-pricing', [ApiPricingController::class, 'store']);
            Route::put('/api-pricing/{apiPricing}', [ApiPricingController::class, 'update']);
            Route::get('/api-pricing/{apiPricing}/logs', [ApiPricingController::class, 'logs']);

            // Settings & Cache
            Route::get('/settings', [AdminSettingController::class, 'index']);
            Route::post('/settings', [AdminSettingController::class, 'update']);
            Route::get('/integrations', [\App\Http\Controllers\Api\Admin\IntegrationController::class, 'index']);
            Route::post('/integrations', [\App\Http\Controllers\Api\Admin\IntegrationController::class, 'update']);
            Route::get('/payment-settings', [PaymentSettingsController::class, 'index']);
            Route::put('/payment-settings', [PaymentSettingsController::class, 'update']);
            Route::post('/settings/clear-cache', [AdminSettingController::class, 'clearCache']);

            // Analytics
            Route::prefix('analytics')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'index']);
                Route::get('/behavior', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'behavior']);
                Route::get('/acquisition', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'acquisition']);
                Route::get('/conversion', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'conversion']);
                Route::get('/monetization', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'monetization']);
                Route::get('/subscriptions', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'subscriptions']);
                Route::get('/realtime', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'realtimeData']);
            });

            // Server Monitor
            Route::prefix('monitor')->group(function () {
                Route::get('/realtime', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'realtime']);
                Route::get('/history', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'history']);
                Route::get('/queues', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'queues']);
                Route::get('/logs', [\App\Http\Controllers\Api\Admin\SystemLogController::class, 'index']);
            });


            // Simulation Engine Models (Motor de Simulados)
            Route::prefix('simulation-models')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\AdminSimulationModelsController::class, 'index']);
                Route::get('/{simulationModel}', [\App\Http\Controllers\Api\Admin\AdminSimulationModelsController::class, 'show']);
                Route::post('/{simulationModel}/rule', [\App\Http\Controllers\Api\Admin\AdminSimulationModelsController::class, 'updateRule']);
                Route::post('/{simulationModel}/toggle', [\App\Http\Controllers\Api\Admin\AdminSimulationModelsController::class, 'toggle']);
            });

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
                Route::post('/{batchId}/cancel-and-revert', [AdminAIBatchTriageController::class, 'cancelAndRevert']);
            });

            // AI Batch History & Rollback
            Route::prefix('questions-batch')->group(function () {
                Route::get('/history', [AdminAIBatchTriageController::class, 'history']);
                Route::get('/details/{batchId}', [AdminAIBatchTriageController::class, 'details']);
                Route::post('/undo-batch/{batchId}', [AdminAIBatchTriageController::class, 'undoBatch']);
                Route::post('/undo-item/{itemId}', [AdminAIBatchTriageController::class, 'undoItem']);
                Route::post('/retry/{batchId}', [AdminAIBatchTriageController::class, 'retry']);
            });

            // Simulation Builder
            Route::prefix('simulations')->group(function () {
                Route::get('/presets', [AdminSimulationController::class, 'index']);
                Route::post('/presets', [AdminSimulationController::class, 'store']);
                Route::get('/presets/{preset}', [AdminSimulationController::class, 'show']);
                Route::put('/presets/{preset}', [AdminSimulationController::class, 'update']);
                Route::delete('/presets/{preset}', [AdminSimulationController::class, 'destroy']);
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

            // Xavier Insights
            Route::prefix('xavier')->group(function () {
                Route::get('/insights', [\App\Http\Controllers\Api\Admin\XavierInsightsController::class, 'index']);
                Route::get('/history', [\App\Http\Controllers\Api\Admin\XavierInsightsController::class, 'history']);
            });

            // Admin Question Import
            Route::prefix('import')->group(function () {
                Route::get('/', [AdminQuestionImportController::class, 'index']);
                Route::post('/', [AdminQuestionImportController::class, 'store']);
                Route::get('/active-job', [AdminQuestionImportController::class, 'activeJob']);
                Route::get('/{id}/progress', [AdminQuestionImportController::class, 'progress']);
                Route::delete('/{id}', [AdminQuestionImportController::class, 'destroy']);
            });
        });
    });
});
