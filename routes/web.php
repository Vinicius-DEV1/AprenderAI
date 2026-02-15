<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\EssayController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/webhooks/mercadopago', [\App\Http\Controllers\WebhookController::class, 'handleMercadoPago'])->name('webhooks.mercadopago');
Route::post('/webhooks/asaas', [\App\Http\Controllers\WebhookController::class, 'handleAsaas'])->name('webhooks.asaas');

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Simulados
    Route::prefix('simulations')->name('simulations.')->group(function () {
        Route::get('/', [SimulationController::class, 'index'])->name('index');
        Route::get('/create', [SimulationController::class, 'create'])
            ->middleware('check.plan.limits:simulation')
            ->name('create');
        Route::post('/', [SimulationController::class, 'store'])->name('store');
        Route::get('/{simulation}', [SimulationController::class, 'show'])->name('show');
        Route::post('/{simulation}/answer', [SimulationController::class, 'saveAnswer'])->name('answer');
        Route::post('/{simulation}/finish', [SimulationController::class, 'finish'])->name('finish');
        Route::get('/{simulation}/result', [SimulationController::class, 'result'])->name('result');
    });

    // Redações
    Route::prefix('essays')->name('essays.')->group(function () {
        Route::get('/', [EssayController::class, 'index'])->name('index');
        Route::get('/create', [EssayController::class, 'create'])
            ->middleware('check.plan.limits:essay')
            ->name('create');
        Route::post('/', [EssayController::class, 'store'])->name('store');
        Route::post('/{essay}/submit', [EssayController::class, 'submit'])->name('submit');
        Route::get('/{essay}', [EssayController::class, 'show'])->name('show');
    });

    // Planos
    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index');
        Route::get('/{plan}', [PlanController::class, 'show'])->name('show');
        Route::get('/{plan}/checkout', [\App\Http\Controllers\SubscriptionController::class, 'showCheckout'])->name('checkout');
        Route::post('/{plan}/validate-coupon', [\App\Http\Controllers\SubscriptionController::class, 'validateCoupon'])->name('validate-coupon');
        Route::post('/{plan}/checkout', [\App\Http\Controllers\SubscriptionController::class, 'store'])
            ->middleware('check.payment.active')
            ->name('store');
    });

    // Pagamentos
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/success', [\App\Http\Controllers\SubscriptionController::class, 'success'])->name('success');
        Route::get('/failure', [\App\Http\Controllers\SubscriptionController::class, 'failure'])->name('failure');
        Route::get('/pending', [\App\Http\Controllers\SubscriptionController::class, 'pending'])->name('pending');
    });

    // Admin
    Route::middleware(['is.admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/api-keys', [\App\Http\Controllers\Admin\AdminController::class, 'apiKeys'])->name('api-keys');
        Route::post('/api-keys', [\App\Http\Controllers\Admin\AdminController::class, 'storeApiKey'])->name('api-keys.store');
        Route::patch('/api-keys/{apiKey}/toggle', [\App\Http\Controllers\Admin\AdminController::class, 'toggleApiKey'])->name('api-keys.toggle');
        Route::delete('/api-keys/{apiKey}', [\App\Http\Controllers\Admin\AdminController::class, 'destroyApiKey'])->name('api-keys.destroy');
        
        // Configurações de Pagamento
        Route::get('/payment-settings', [\App\Http\Controllers\Admin\PaymentSettingController::class, 'index'])->name('payment-settings');
        Route::post('/payment-settings', [\App\Http\Controllers\Admin\PaymentSettingController::class, 'update'])->name('payment-settings.update');

        // Cupons
        Route::resource('coupons', \App\Http\Controllers\Admin\CouponController::class);

        // Integrações
        Route::get('/integrations', [\App\Http\Controllers\Admin\IntegrationController::class, 'index'])->name('integrations');
        Route::post('/integrations', [\App\Http\Controllers\Admin\IntegrationController::class, 'update'])->name('integrations.update');

        // Usuários
        Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle-status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
    });
});

// Google Auth
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('auth.google.callback');

require __DIR__ . '/auth.php';
