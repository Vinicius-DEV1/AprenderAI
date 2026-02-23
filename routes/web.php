<?php

use App\Http\Controllers\ConcursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\EssayController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $plans = \App\Models\Plan::where('is_active', true)->get();
    return view('welcome', compact('plans'));
})->name('home');

Route::view('/privacidade', 'legal.privacy')->name('privacy');
Route::view('/uso-justo', 'legal.fair-use')->name('fair-use');

Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookController::class , 'handleAsaas'])->name('webhooks.asaas');

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class , 'index'])->name('dashboard');

    // Simulados
    Route::prefix('simulations')->name('simulations.')->group(
        function () {
            Route::get('/', [SimulationController::class , 'index'])->name('index');
            Route::get('/create', [SimulationController::class , 'create'])
                ->middleware('check.plan.limits:simulation')
                ->name('create');
            Route::post('/', [SimulationController::class , 'store'])->name('store');
            Route::get('/{simulation}', [SimulationController::class , 'show'])->name('show');
            Route::post('/{simulation}/answer', [SimulationController::class , 'saveAnswer'])->name('answer');
            Route::post('/{simulation}/finish', [SimulationController::class , 'finish'])->name('finish');
            Route::get('/{simulation}/result', [SimulationController::class , 'result'])->name('result');
            Route::get('/{simulation}/status', [SimulationController::class , 'checkCorrectionStatus'])->name('status');
            Route::post('/{simulation}/essay', [EssayController::class , 'storeForSimulation'])->name('essay.store');

            // Chat Contextual
            Route::post('/{simulation}/questions/{question}/chat', [\App\Http\Controllers\QuestionChatController::class , 'store'])->name('questions.chat.store');
            Route::post('/{simulation}/questions/{question}/chat/stream', [\App\Http\Controllers\QuestionChatController::class , 'stream'])->name('questions.chat.stream');
            Route::get('/{simulation}/questions/{question}/chat', [\App\Http\Controllers\QuestionChatController::class , 'index'])->name('questions.chat.index');
        }
        );

        // Banco de Questões (Aluno)
        Route::prefix('questions')->name('questions.')->group(
            function () {
            Route::get('/', [\App\Http\Controllers\QuestionBankController::class , 'index'])->name('index');
            Route::get('/topics', [\App\Http\Controllers\QuestionBankController::class , 'topics'])->name('topics');
            Route::get('/subjects', [\App\Http\Controllers\QuestionBankController::class , 'subjects'])->name('subjects');
            Route::post('/ai-search', [\App\Http\Controllers\AiSearchController::class , 'search'])->name('ai-search');
            Route::get('/ai-search/{searchRequest}/status', [\App\Http\Controllers\AiSearchController::class , 'status'])->name('ai-search.status');
            Route::post('/{question}/answer', [\App\Http\Controllers\QuestionBankController::class , 'answer'])->name('answer');
            Route::get('/stats', [\App\Http\Controllers\QuestionBankController::class , 'stats'])->name('stats');
            // Histórico individual de respostas (AJAX)
            Route::get('/{question}/history', [\App\Http\Controllers\QuestionBankController::class , 'history'])->name('history');
            // Chat Standalone (Tirar Dúvida)
            Route::post('/{question}/chat', [\App\Http\Controllers\QuestionChatController::class , 'storeStandalone'])->name('chat.store');
            Route::post('/{question}/chat/stream', [\App\Http\Controllers\QuestionChatController::class , 'streamStandalone'])->name('chat.stream');
            Route::get('/{question}/chat', [\App\Http\Controllers\QuestionChatController::class , 'indexStandalone'])->name('chat.index');
        }
        );

        // Recarga de Redações (definido antes do grupo essays para evitar conflito com /{essay})
        Route::match (['get', 'post'], '/essays/recharge', [\App\Http\Controllers\EssayRechargeController::class , 'store'])->name('recharge');
        Route::get('/essays/recharge-status', [\App\Http\Controllers\EssayRechargeController::class , 'checkStatus'])->name('recharge.check-status');

        // Redações
        Route::prefix('essays')->name('essays.')->group(
            function () {
            Route::get('/', [EssayController::class , 'index'])->name('index');
            Route::get('/create', [EssayController::class , 'create'])->name('create');
            Route::post('/', [EssayController::class , 'store'])->name('store');

            // New Multi-step routes
            Route::get('/{essay}/topic', [EssayController::class , 'showTopic'])->name('topic');
            // Async Topic Routes
            Route::post('/{essay}/start-topic', [EssayController::class , 'startTopicGeneration'])->name('start-topic');
            Route::get('/{essay}/topic-status', [EssayController::class , 'getTopicStatus'])->name('topic-status');

            // Deprecated/Legacy (keep if needed or remove? Plan implies replacing logic but keeping names if referenced. 
            // User asked for specific routes. I will keep existing generate-topic redirected or just use new ones.)
            // Route::post('/{essay}/topic', [EssayController::class, 'generateTopic'])->name('generate-topic');
    
            Route::get('/{essay}/write', [EssayController::class , 'write'])->name('write');

            Route::post('/{essay}/submit', [EssayController::class , 'submit'])->name('submit');
            Route::post('/{essay}/retry-evaluation', [EssayController::class , 'retryEvaluation'])->name('retry-evaluation');

            Route::get('/{essay}', [EssayController::class , 'show'])->name('show');
        }
        );



        // Plano de Estudos
        Route::prefix('study-plan')->name('study-plan.')->group(
            function () {
            Route::get('/', [\App\Http\Controllers\StudyPlanController::class , 'index'])->name('index');
            Route::get('/status', [\App\Http\Controllers\StudyPlanController::class , 'status'])->name('status');
            Route::post('/generate', [\App\Http\Controllers\StudyPlanController::class , 'store'])->name('store');
            Route::post('/update', [\App\Http\Controllers\StudyPlanController::class , 'update'])->name('update');
        }
        );

        // Planos
        Route::prefix('plans')->name('plans.')->group(
            function () {
            Route::get('/', [PlanController::class , 'index'])->name('index');
            Route::get('/{plan}', [PlanController::class , 'show'])->name('show');
            Route::get('/{plan}/checkout', [\App\Http\Controllers\SubscriptionController::class , 'showCheckout'])->name('checkout');
            Route::post('/{plan}/validate-coupon', [\App\Http\Controllers\SubscriptionController::class , 'validateCoupon'])->name('validate-coupon');
            Route::get('/check-status', [\App\Http\Controllers\SubscriptionController::class , 'checkStatus'])->name('check-status');
            Route::post('/{plan}/checkout', [\App\Http\Controllers\SubscriptionController::class , 'store'])
                ->middleware('check.payment.active')
                ->name('store');
        }
        );

        // Perfil do Usuário
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ProfileController::class , 'index'])->name('index');
            Route::put('/', [\App\Http\Controllers\ProfileController::class , 'update'])->name('update');
            Route::put('/password', [\App\Http\Controllers\ProfileController::class , 'updatePassword'])->name('password.update');
        }
        );

        // Pagamentos
        Route::prefix('payments')->name('payments.')->group(
            function () {
            Route::get('/success', [\App\Http\Controllers\SubscriptionController::class , 'success'])->name('success');
            Route::get('/failure', [\App\Http\Controllers\SubscriptionController::class , 'failure'])->name('failure');
            Route::get('/pending', [\App\Http\Controllers\SubscriptionController::class , 'pending'])->name('pending');
        }
        );

        // Checkout Intermediário (Conversão)
        Route::prefix('checkout')->name('checkout.')->group(
            function () {
            Route::get('/welcome', [\App\Http\Controllers\CheckoutController::class , 'welcome'])->name('welcome');
            Route::get('/skip', [\App\Http\Controllers\CheckoutController::class , 'skip'])->name('skip');
        }
        );

        // Onboarding / Fluxo de Boas-vindas
        Route::get('/bem-vindo', [\App\Http\Controllers\OnboardingController::class , 'welcome'])->name('onboarding.welcome');

        // Admin
        Route::middleware(['is.admin'])->prefix('admin')->name('admin.')->group(
            function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminController::class , 'dashboard'])->name('dashboard');
            Route::get('/curadoria', [\App\Http\Controllers\Admin\CuradoriaController::class , 'index'])->name('curadoria');
            Route::get('/api-keys', [\App\Http\Controllers\Admin\AdminController::class , 'apiKeys'])->name('api-keys');
            Route::post('/api-keys/vault', [\App\Http\Controllers\Admin\AdminController::class , 'storeVaultKey'])->name('api-keys.vault.store');
            Route::post('/api-keys/discover', [\App\Http\Controllers\Admin\AdminController::class , 'discoverModels'])->name('api-keys.discover');
            Route::post('/api-keys', [\App\Http\Controllers\Admin\AdminController::class , 'storeApiKey'])->name('api-keys.store');
            Route::post('/api-keys/priority', [\App\Http\Controllers\Admin\AdminController::class , 'updateCapabilitiesPriority'])->name('api-keys.update-priority');
            Route::patch('/api-keys/{apiKey}/toggle', [\App\Http\Controllers\Admin\AdminController::class , 'toggleApiKey'])->name('api-keys.toggle');
            Route::post('/api-keys/test-connection', [\App\Http\Controllers\Admin\AdminController::class , 'testConnection'])->name('api-keys.test');
            Route::post('/api-keys/clear-logs', [\App\Http\Controllers\Admin\AdminController::class , 'clearLogs'])->name('api-keys.clear-logs');
            Route::post('/api-keys/{apiKey}/retest', [\App\Http\Controllers\Admin\AdminController::class , 'retestApiKey'])->name('api-keys.retest');
            Route::delete('/api-keys/{apiKey}', [\App\Http\Controllers\Admin\AdminController::class , 'destroyApiKey'])->name('api-keys.destroy');

            // Configurações de Pagamento
            Route::get('/payment-settings', [\App\Http\Controllers\Admin\PaymentSettingController::class , 'index'])->name('payment-settings');
            Route::post('/payment-settings', [\App\Http\Controllers\Admin\PaymentSettingController::class , 'update'])->name('payment-settings.update');

            // Cupons
            Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class);

            // Integrações
            Route::get('/integrations', [\App\Http\Controllers\Admin\IntegrationController::class , 'index'])->name('integrations');
            Route::post('/integrations', [\App\Http\Controllers\Admin\IntegrationController::class , 'update'])->name('integrations.update');

            // Usuários
            Route::get('/users', [\App\Http\Controllers\Admin\UserController::class , 'index'])->name('users.index');
            Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class , 'show'])->name('users.show');
            Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class , 'update'])->name('users.update');
            Route::patch('/users/{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class , 'toggleStatus'])->name('users.toggle-status');
            Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class , 'resetPassword'])->name('users.reset-password');

            // Planos
            Route::resource('plans', \App\Http\Controllers\Admin\PlanController::class);

            // Monitoramento
            Route::get('/monitor', [\App\Http\Controllers\Admin\MonitorController::class , 'index'])->name('monitor.index');
            Route::get('/monitor/realtime', [\App\Http\Controllers\Admin\MonitorController::class , 'realtime'])->name('monitor.realtime');
            Route::get('/monitor/history', [\App\Http\Controllers\Admin\MonitorController::class , 'history'])->name('monitor.history');

            // Questões (Banco de Questões)
            Route::resource('questions', \App\Http\Controllers\Admin\QuestionController::class);
            Route::post('questions/{question}/evaluate-difficulty', [\App\Http\Controllers\Admin\QuestionController::class , 'evaluateDifficulty'])->name('questions.evaluate-difficulty');
            Route::post('questions/batch-evaluate-difficulty', [\App\Http\Controllers\Admin\QuestionController::class , 'batchEvaluateDifficulty'])->name('questions.batch-evaluate-difficulty');
            Route::post('questions/{question}/generate-explanation', [\App\Http\Controllers\Admin\QuestionController::class , 'generateExplanation'])->name('questions.generate-explanation');
            Route::post('questions/{question}/complete', [\App\Http\Controllers\Admin\QuestionController::class , 'completeQuestion'])->name('questions.complete');
            Route::post('questions/{question}/classify', [\App\Http\Controllers\Admin\QuestionController::class , 'classifyQuestion'])->name('questions.classify');
            Route::post('questions/batch-complete', [\App\Http\Controllers\Admin\QuestionController::class , 'batchCompleteQuestions'])->name('questions.batch-complete');

            // Novo Batch Triage (Asíncrono)
            Route::prefix('questions-batch')->name('questions.batch.')->group(function () {
                    Route::post('/start', [\App\Http\Controllers\Admin\AIBatchTriageController::class , 'start'])->name('start');
                    Route::get('/progress/{batch_id}', [\App\Http\Controllers\Admin\AIBatchTriageController::class , 'progress'])->name('progress');
                    Route::get('/history', [\App\Http\Controllers\Admin\AIBatchTriageController::class , 'history'])->name('history');
                }
                );
                Route::get('/admin/triagem/historico', [\App\Http\Controllers\Admin\AIBatchTriageController::class , 'history'])->name('triagem.historico');

                // ----------------------------------------------------------------
                // Módulo de Importação de Questões (scraper.py → .zip → produção)
                // ----------------------------------------------------------------
                Route::prefix('import')->name('import.')->group(function () {
                    // Tela principal de upload + histórico de lotes
                    Route::get('/', [\App\Http\Controllers\Admin\QuestionImportController::class , 'index'])
                        ->name('index');

                    // Processa o .zip enviado (upload + extração + importação)
                    Route::post('/', [\App\Http\Controllers\Admin\QuestionImportController::class , 'store'])
                        ->name('store');

                    // Busca se há trabalho em progresso ativo do usuário atual
                    Route::get('/active-job', [\App\Http\Controllers\Admin\QuestionImportController::class , 'activeJob'])
                        ->name('active-job');

                    // Polling do progresso da importação via Background Jobs
                    Route::get('/{import}/progress', [\App\Http\Controllers\Admin\QuestionImportController::class , 'progress'])
                        ->name('progress');

                    // Painel de revisão: lista questões pendentes + histórico de aprovações
                    Route::get('/review', [\App\Http\Controllers\Admin\QuestionImportController::class , 'reviewIndex'])
                        ->name('review.index');

                    // Inspeção visual individual + editor de crop (Cropper.js)
                    Route::get('/review/{question}', [\App\Http\Controllers\Admin\QuestionImportController::class , 'reviewShow'])
                        ->name('review.show');

                    // Salva um recorte de imagem como alternativa (coordenadas do Cropper.js → GD → storage)
                    Route::post('/review/{image}/crop', [\App\Http\Controllers\Admin\QuestionImportController::class , 'crop'])
                        ->name('review.crop');

                    // Remove a imagem principal de uma questão
                    Route::delete('/review/{image}/image', [\App\Http\Controllers\Admin\QuestionImportController::class , 'deleteImage'])
                        ->name('review.delete-image');

                    // Aprova a questão (pending → approved, registra no log de auditoria)
                    Route::post('/review/{question}/approve', [\App\Http\Controllers\Admin\QuestionImportController::class , 'approve'])
                        ->name('review.approve');

                    // Reverte a questão para revisão (approved → pending)
                    Route::post('/review/{question}/revert', [\App\Http\Controllers\Admin\QuestionImportController::class , 'revert'])
                        ->name('review.revert');
                }
                );

                // ----------------------------------------------------------------
                // Módulo de Importação da API ENEM Dev
                // ----------------------------------------------------------------
                Route::prefix('enem-import')->name('enem-import.')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Admin\EnemImportController::class , 'index'])->name('index');
                    Route::post('/', [\App\Http\Controllers\Admin\EnemImportController::class , 'store'])->name('store');
                    Route::get('/status', [\App\Http\Controllers\Admin\EnemImportController::class , 'status'])->name('status');
                }
                );

                // Chat Logs
                Route::get('/chat-logs/{id}', [\App\Http\Controllers\Admin\ChatLogController::class , 'show'])->name('chat-logs.show');

                // Gerenciador de Prompts
                Route::get('/prompts', [\App\Http\Controllers\Admin\SystemPromptController::class , 'index'])->name('prompts.index');
                Route::get('/prompts/{systemPrompt}/edit', [\App\Http\Controllers\Admin\SystemPromptController::class , 'edit'])->name('prompts.edit');
                Route::put('/prompts/{systemPrompt}', [\App\Http\Controllers\Admin\SystemPromptController::class , 'update'])->name('prompts.update');
                Route::post('/prompts/{systemPrompt}/clear-cache', [\App\Http\Controllers\Admin\SystemPromptController::class , 'clearCache'])->name('prompts.clear-cache');

                // Configurações do Site
                Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class , 'index'])->name('settings.index');
                Route::post('/settings', [\App\Http\Controllers\Admin\SettingController::class , 'update'])->name('settings.update');
            }
            );

            // Concursos
            Route::get('/concursos', [ConcursoController::class , 'index'])->name('concursos.index');        });

// Google Auth
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class , 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class , 'callback'])->name('auth.google.callback');

require __DIR__ . '/auth.php';
