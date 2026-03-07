<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Jobs\ProcessAsaasWebhookJob;

echo "--- 7. AUDITORIA DO EMAIL DE ASSINATURA ---\n";

$user = User::first();
if (!$user) {
    die("Nenhum usuario encontrado.\n");
}

$plan = Plan::firstOrCreate(
    ['slug' => 'premium-mensal'],
    ['name' => 'Premium Mensal', 'price' => 29.90, 'monthly_price' => 29.90, 'interval' => 'monthly', 'is_active' => true]
);

$subscription = Subscription::create([
    'user_id' => $user->id,
    'plan_id' => $plan->id,
    'gateway_id' => 'sub_audit_' . time(),
    'status' => 'pending'
]);

$payload = [
    'event' => 'PAYMENT_CONFIRMED',
    'payment' => [
        'id' => 'pay_audit_' . time(),
        'subscription' => $subscription->gateway_id,
        'value' => 29.90,
        'paymentDate' => now()->toDateString()
    ],
    'is_sandbox_webhook' => true
];

echo "DISPATCHING_WEBHOOK_JOB: PAYMENT_CONFIRMED\n";
dispatch(new ProcessAsaasWebhookJob($payload));
echo "WEBHOOK_JOB_DISPATCHED\n";
