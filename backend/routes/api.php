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
use App\Http\Controllers\Api\Admin\AdminGrantController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\SystemPromptController as AdminSystemPromptController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\ApiKeyController as AdminApiKeyController;
use App\Http\Controllers\Api\Admin\AIBatchJobController as AdminAIBatchJobController;
use App\Http\Controllers\Api\Admin\AIBatchAnalyticsController as AdminAIBatchAnalyticsController;
use App\Http\Controllers\Api\Admin\EnemImportController as AdminEnemImportController;
use App\Http\Controllers\Api\Admin\AdminEssayController;
use App\Http\Controllers\Api\Admin\AdminQuestionImportController;
use App\Http\Controllers\Api\Admin\AdminImportReviewController;
use App\Http\Controllers\Api\Admin\AdminSimulationController;
use App\Http\Controllers\Api\Admin\ApiPricingController;
use App\Http\Controllers\Api\Admin\PaymentSettingsController;
use App\Http\Controllers\Api\Admin\ExamController;
use App\Http\Controllers\Api\Admin\BackupController;
use App\Http\Controllers\Api\Admin\SemanticDashboardController;
use App\Http\Controllers\Api\Admin\SemanticTestController;
use App\Http\Controllers\Api\Admin\SemanticActionController;
use App\Http\Controllers\Api\CheckoutTrackingController;
use App\Http\Controllers\Api\Admin\CheckoutAnalyticsController;
use App\Http\Controllers\Api\PlatformTrackingController;
use App\Http\Controllers\Api\Admin\AdminPlatformMonitorController;
use App\Http\Controllers\Api\Admin\PlatformEngagementController;
use App\Http\Controllers\Api\Admin\UserSessionMonitorController;
use App\Http\Controllers\Api\HealthController;

// Engagement System
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\FeatureFeedbackController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\Admin\SupportAdminController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\NotificationAdminController;
use App\Http\Controllers\Api\Admin\FeedbackAdminController;
use App\Http\Controllers\Api\Admin\SuggestionAdminController;

/*
|--------------------------------------------------------------------------
| API Routes — Prefixo: /api/v1
|--------------------------------------------------------------------------
*/

// -----------------------------------------------------------------------
// Rota pública de saúde — NÃO requer autenticação.
// Usada por:
//  1. Docker healthcheck (test: curl /api/health)
//  2. Frontend deploy detection (useDeployDetection hook)
// -----------------------------------------------------------------------
Route::get('/health', [HealthController::class, 'check'])->name('api.health');

Route::prefix('v1')->group(function () {
    // Webhooks
    Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookController::class, 'handleAsaas'])->name('api.webhooks.asaas');

    // Público
    Route::get('/config', [ConfigController::class, 'index'])->name('api.config');
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::get('/login', function () {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    })->name('login');
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
        Route::post('simulations/{simulation}/heartbeat', [SimulationController::class, 'heartbeat']);
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
        // Essay abandonment notifications
        Route::post('essays/{essay}/notify-pending', [EssayController::class, 'notifyPending']);
        Route::post('essays/{essay}/mark-pending-done', [EssayController::class, 'markPendingDone']);

        // Subscriptions
        Route::prefix('subscriptions')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index']);
            Route::post('/{plan}/validate-coupon', [SubscriptionController::class, 'validateCoupon']);
            Route::post('/{plan}/checkout', [SubscriptionController::class, 'store']);
            Route::get('/{plan}/upgrade-preview', [SubscriptionController::class, 'upgradePreview']);
            Route::get('/check-status', [SubscriptionController::class, 'checkStatus']);
            Route::get('/{subscription}/receipt', [SubscriptionController::class, 'receiptUrl']);
            // Pix payment recovery
            Route::get('/pending-pix', [SubscriptionController::class, 'pendingPix']);
            Route::post('/{subscription}/regenerate-pix', [SubscriptionController::class, 'regeneratePix']);
        });

        // Checkout Tracking (fire-and-forget events from the frontend)
        Route::prefix('tracking')->group(function () {
            Route::post('/intention', [CheckoutTrackingController::class, 'recordIntention']);
            Route::post('/event', [CheckoutTrackingController::class, 'trackEvent']);
            Route::post('/error', [CheckoutTrackingController::class, 'trackFrontendError']);
            Route::post('/abandonment', [CheckoutTrackingController::class, 'recordAbandonment']);
        });

        // Platform Monitoring Tracking (native, internal — all authenticated users)
        Route::prefix('platform')->group(function () {
            Route::post('/session/start', [PlatformTrackingController::class, 'sessionStart']);
            Route::post('/session/end', [PlatformTrackingController::class, 'sessionEnd']);
            Route::post('/heartbeat', [PlatformTrackingController::class, 'heartbeat']);
            Route::post('/event', [PlatformTrackingController::class, 'event']);
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
            Route::get('/stats', [\App\Http\Controllers\Api\QuestionStatsController::class, 'stats']);
            Route::get('/{question}/history', [\App\Http\Controllers\Api\QuestionStatsController::class, 'history']);
            Route::post('/{question}/view', [QuestionController::class, 'logView']);
            Route::post('/{question}/answer', [QuestionController::class, 'answer'])
                ->middleware('check.plan.limits:daily_question');

            // Notes, Favorites, Reports, and Stats
            Route::post('/{question}/favorite', [FavoriteController::class, 'toggle']);
            Route::post('/{question}/report', [QuestionReportController::class, 'store']);
            Route::get('/{question}/stats', [\App\Http\Controllers\Api\QuestionStatsController::class, 'questionStats']);
            Route::get('/{question}/notes', [QuestionNoteController::class, 'index']);
            Route::post('/{question}/notes', [QuestionNoteController::class, 'store']);
            Route::put('/notes/{note}', [QuestionNoteController::class, 'update']);
            Route::delete('/notes/{note}', [QuestionNoteController::class, 'destroy']);

            // Xavier AI Search
            Route::post('/ai-search', [\App\Http\Controllers\Api\XavierSearchController::class, 'aiSearch'])->name('questions.ai-search');
            Route::get('/ai-search/{aiSearchRequest}/status', [\App\Http\Controllers\Api\XavierSearchController::class, 'aiSearchStatus'])->name('questions.ai-search.status');

            // Xavier Chat
            Route::get('/{question}/chat', [\App\Http\Controllers\Api\QuestionChatController::class, 'chat'])->name('questions.chat.index');
            Route::post('/{question}/chat', [\App\Http\Controllers\Api\QuestionChatController::class, 'sendChat'])->name('questions.chat.store');

            // Engagement & Goals
            Route::get('/engagement', [\App\Http\Controllers\Api\QuestionGoalController::class, 'engagement']);
            Route::post('/goal', [\App\Http\Controllers\Api\QuestionGoalController::class, 'updateGoal']);
        });

        // Profile
        Route::prefix('user')->group(function () {
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'updatePassword']);
        });

        // ── Engagement: Support Chat (user-facing) ────────────────────────────
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [SupportController::class, 'index']);
            Route::post('/tickets', [SupportController::class, 'store']);
            Route::get('/tickets/{id}', [SupportController::class, 'show']);
            Route::post('/tickets/{id}/messages', [SupportController::class, 'sendMessage']);
        });

        // ── Engagement: Banners (user-facing) ─────────────────────────────────
        Route::prefix('banners')->group(function () {
            Route::get('/active', [BannerController::class, 'active']);
            Route::post('/{banner}/interact', [BannerController::class, 'interact']);
        });

        // ── Engagement: Notifications (user-facing) ───────────────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('/{id}/read', [NotificationController::class, 'markRead']);
        });

        // ── Engagement: Feature Feedback 👍/👎 ────────────────────────────────
        Route::post('/feedback', [FeatureFeedbackController::class, 'store']);

        // ── Engagement: User Suggestions ──────────────────────────────────────
        Route::prefix('suggestions')->group(function () {
            Route::get('/', [SuggestionController::class, 'index']);
            Route::post('/', [SuggestionController::class, 'store']);
            Route::post('/{id}/vote', [SuggestionController::class, 'vote']);
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
            Route::get('exams/explorer/{organization}', [ExamController::class, 'explorer']);
            Route::get('exams/{id}', [ExamController::class, 'show']);

            // Administrative CRUDs
            Route::get('questions/support-data', [AdminQuestionController::class, 'supportData']);
            Route::get('questions/stats/classification-ranking', [AdminQuestionController::class, 'classificationRanking']);
            Route::get('questions/trashed', [AdminQuestionController::class, 'trashed']);

            Route::post('questions/{id}/restore', [AdminQuestionController::class, 'restore']);
            Route::delete('questions/{id}/force', [AdminQuestionController::class, 'forceDelete']);

            Route::get('questions/{question}/delete-impact', [AdminQuestionController::class, 'deleteImpact']);
            Route::get('questions/{question}/triage-history', [AdminImportReviewController::class, 'triageHistory']);
            Route::apiResource('questions', AdminQuestionController::class);
            Route::post('questions/{question}/evaluate-difficulty', [AdminQuestionController::class, 'evaluateDifficulty']);
            Route::post('questions/{question}/generate-explanation', [AdminQuestionController::class, 'generateExplanation']);
            Route::post('questions/{question}/complete', [AdminQuestionController::class, 'completeQuestion']);
            Route::post('questions/{question}/classify', [AdminQuestionController::class, 'classifyQuestion']);
            Route::post('questions/{question}/retry-evaluation', [AdminQuestionController::class, 'completeQuestion']); // Re-use completeQuestion for full retry
            Route::post('questions/{question}/revert-triage', [AdminQuestionController::class, 'revertToTriage']);

            Route::patch('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus']);
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
            Route::post('users/{user}/refund', [AdminUserController::class, 'refundAndCancel']);
            Route::post('users/{user}/grant-plan', [AdminGrantController::class, 'grant']);
            Route::delete('users/{user}/revoke-grant', [AdminGrantController::class, 'revoke']);
            Route::get('users/grants', [AdminGrantController::class, 'index']);
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
                Route::get('/utm-cohorts', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'utmCohorts']);
                Route::get('/realtime', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'realtimeData']);
                Route::post('/sync', [\App\Http\Controllers\Api\Admin\AdminAnalyticsController::class, 'syncNow']);
            });

            // Campaigns (Operational)
            Route::prefix('campaigns')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'store']);
                Route::get('/{campaign}', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'show']);
                Route::put('/{campaign}', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'update']);
                Route::delete('/{campaign}', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'destroy']);
                Route::post('/{campaign}/toggle', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'toggle']);
            });

            // Server Monitor
            Route::prefix('monitor')->group(function () {
                // Shared overview & failed jobs (considolated under admin)
                Route::get('/overview', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'overview']);
                Route::prefix('failed-jobs')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'listFailedJobs']);
                    Route::delete('/', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'clearFailedJobs']);
                    Route::post('/retry-all', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'retryAllFailedJobs']);
                    Route::post('/{id}/retry', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'retryFailedJob']);
                });

                Route::get('/realtime', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'realtime']);
                Route::get('/history', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'history']);
                Route::get('/queues', [\App\Http\Controllers\Api\Admin\MonitorController::class, 'queues']);
                Route::get('/logs', [\App\Http\Controllers\Api\Admin\SystemLogController::class, 'index']);
                Route::delete('/logs', [\App\Http\Controllers\Api\Admin\SystemLogController::class, 'clear']);
                Route::delete('/pending-triage', [\App\Http\Controllers\Api\WorkerMonitorController::class, 'clearPendingTriage']);
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
            Route::put('/api-keys/vault/{vault}', [AdminApiKeyController::class, 'updateVault']);
            Route::delete('/api-keys/vault/{vault}', [AdminApiKeyController::class, 'destroyVault']);
            Route::post('/api-keys/{apiKey}/toggle', [AdminApiKeyController::class, 'toggle']);
            Route::post('/api-keys/priority', [AdminApiKeyController::class, 'updatePriority']);
            Route::post('/api-keys/discover', [AdminApiKeyController::class, 'discoverModels']);
            Route::post('/api-keys/{apiKey}/retest', [AdminApiKeyController::class, 'retest']);
            Route::delete('/api-keys/{id}', [AdminApiKeyController::class, 'destroy']);

            // AI Batch Triage
            Route::prefix('triage')->group(function () {
                Route::post('/preview', [AdminAIBatchJobController::class, 'preview']);
                Route::post('/start', [AdminAIBatchJobController::class, 'start']);
                Route::get('/active', [AdminAIBatchAnalyticsController::class, 'active']);
                Route::get('/active-keys', [AdminAIBatchAnalyticsController::class, 'activeKeys']);
                Route::get('/{batchId}/status', [AdminAIBatchAnalyticsController::class, 'status']);
                Route::get('/{batchId}/details', [AdminAIBatchAnalyticsController::class, 'details']);
                Route::post('/{batchId}/cancel', [AdminAIBatchJobController::class, 'cancel']);
                Route::post('/{batchId}/cancel-and-revert', [AdminAIBatchJobController::class, 'cancelAndRevert']);
            });

            Route::prefix('questions-batch')->group(function () {
                Route::get('/history', [AdminAIBatchAnalyticsController::class, 'history']);
                Route::get('/details/{batchId}', [AdminAIBatchAnalyticsController::class, 'details']);
                Route::post('/undo-batch/{batchId}', [AdminAIBatchJobController::class, 'undoBatch']);
                Route::post('/undo-item/{itemId}', [AdminAIBatchJobController::class, 'undoItem']);
                Route::post('/retry/{batchId}', [AdminAIBatchJobController::class, 'retry']);
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
                Route::get('/summary', [AdminImportReviewController::class, 'summary']);
                Route::get('/stats', [AdminImportReviewController::class, 'stats']);
                Route::get('/{id}', [AdminImportReviewController::class, 'show']);
                Route::post('/{id}/approve', [AdminImportReviewController::class, 'approve']);
                Route::post('/{id}/revert', [AdminImportReviewController::class, 'revert']);
                Route::post('/{id}/send-to-triage', [AdminImportReviewController::class, 'sendToTriage']);
                Route::post('/{imageId}/crop', [AdminImportReviewController::class, 'crop']);
                Route::delete('/{imageId}/image', [AdminImportReviewController::class, 'deleteImage']);
            });

            // Xavier Insights
            Route::prefix('xavier')->group(function () {
                Route::get('/insights', [\App\Http\Controllers\Api\Admin\XavierInsightsController::class, 'index']);
                Route::get('/history', [\App\Http\Controllers\Api\Admin\XavierInsightsController::class, 'history']);
            });

            // Semantic Dashboard
            Route::prefix('semantic')->group(function () {
                Route::get('/', [SemanticDashboardController::class, 'index']);
                Route::post('/config', [SemanticDashboardController::class, 'updateConfig']);
                Route::post('/test-search', [SemanticTestController::class, 'testSearch']);
                Route::post('/reindex', [SemanticActionController::class, 'reindexAll']);
                Route::post('/reindex-concepts', [SemanticActionController::class, 'reindexConcepts']);
                Route::post('/clear-cache', [SemanticActionController::class, 'clearCache']);
                Route::post('/clear-queue', [SemanticActionController::class, 'clearQueue']);
                Route::post('/clear-congestion', [SemanticActionController::class, 'clearCongestion']);
                Route::post('/reset-embeddings', [SemanticActionController::class, 'resetEmbeddings']);
            });

            // Admin Question Import
            Route::prefix('import')->group(function () {
                Route::get('/', [AdminQuestionImportController::class, 'index']);
                Route::post('/', [AdminQuestionImportController::class, 'store']);
                Route::get('/active-job', [AdminQuestionImportController::class, 'activeJob']);
                Route::get('/{id}/progress', [AdminQuestionImportController::class, 'progress']);
                Route::delete('/{id}', [AdminQuestionImportController::class, 'destroy']);
            });

            // Checkout Observability Dashboard
            Route::prefix('checkout')->group(function () {
                Route::get('/overview', [\App\Http\Controllers\Api\Admin\CheckoutAnalyticsController::class, 'overview']);
                Route::get('/funnel', [\App\Http\Controllers\Api\Admin\CheckoutFunnelController::class, 'funnel']);
                Route::get('/plans-ranking', [\App\Http\Controllers\Api\Admin\CheckoutFunnelController::class, 'plansRanking']);
                Route::get('/errors', [\App\Http\Controllers\Api\Admin\CheckoutObservabilityController::class, 'errors']);
                Route::get('/user-timeline/{userId}', [\App\Http\Controllers\Api\Admin\CheckoutObservabilityController::class, 'userTimeline']);
                Route::get('/abandonments', [\App\Http\Controllers\Api\Admin\CheckoutObservabilityController::class, 'abandonments']);
                Route::get('/alerts', [\App\Http\Controllers\Api\Admin\CheckoutObservabilityController::class, 'alerts']);
                Route::get('/timeline', [\App\Http\Controllers\Api\Admin\CheckoutObservabilityController::class, 'timeline']);
            });

            // Database Backups
            Route::prefix('backups')->group(function () {
                Route::get('/', [BackupController::class, 'index']);
                Route::post('/trigger', [BackupController::class, 'trigger']);
                Route::get('/{id}/status', [BackupController::class, 'show']);
                Route::get('/{id}/download', [BackupController::class, 'download']);
                Route::post('/settings', [BackupController::class, 'updateSettings']);
                Route::get('/local-dump', [BackupController::class, 'localDump']);
            });

            // ── Engagement: Support Admin ─────────────────────────────────────
            Route::prefix('support')->group(function () {
                Route::get('/tickets', [SupportAdminController::class, 'index']);
                Route::get('/tickets/{id}', [SupportAdminController::class, 'show']);
                Route::post('/tickets/{id}/reply', [SupportAdminController::class, 'reply']);
                Route::patch('/tickets/{id}/status', [SupportAdminController::class, 'updateStatus']);
            });

            // ── Engagement: Banners Admin ──────────────────────────────────────
            Route::prefix('banners')->group(function () {
                Route::get('/', [BannerAdminController::class, 'index']);
                Route::post('/', [BannerAdminController::class, 'store']);
                Route::get('/{banner}', [BannerAdminController::class, 'show']);
                Route::post('/{banner}', [BannerAdminController::class, 'update']); // POST for multipart/form-data
                Route::delete('/{banner}', [BannerAdminController::class, 'destroy']);
                Route::get('/{banner}/stats', [BannerAdminController::class, 'stats']);
            });

            // ── Engagement: Notifications Admin ───────────────────────────────
            Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationAdminController::class, 'index']);
                Route::post('/send', [NotificationAdminController::class, 'send']);
            });

            // ── Engagement: Feature Feedback Admin ────────────────────────────
            Route::get('/feedback', [FeedbackAdminController::class, 'index']);

            // ── Engagement: Suggestions Admin ─────────────────────────────────
            Route::prefix('suggestions')->group(function () {
                Route::get('/', [SuggestionAdminController::class, 'index']);
                Route::patch('/{suggestion}/status', [SuggestionAdminController::class, 'updateStatus']);
            });

            // Platform Monitor Dashboard (native internal analytics)
            Route::prefix('platform-monitor')->group(function () {
                Route::get('/overview', [AdminPlatformMonitorController::class, 'overview']);
                Route::get('/online', [UserSessionMonitorController::class, 'online']);
                Route::get('/logins', [UserSessionMonitorController::class, 'logins']);
                Route::get('/questions', [PlatformEngagementController::class, 'questions']);
                Route::get('/simulations', [PlatformEngagementController::class, 'simulations']);
                Route::get('/essays', [PlatformEngagementController::class, 'essays']);
                Route::get('/activity', [PlatformEngagementController::class, 'activity']);
                Route::get('/user/{id}', [UserSessionMonitorController::class, 'userDetail']);
            });
        });
    });
});
