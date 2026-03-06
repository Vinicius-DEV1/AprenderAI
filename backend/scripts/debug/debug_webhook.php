<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentLog;
use App\Models\User;
use App\Models\Subscription;

$log = PaymentLog::find(4);
if (!$log) {
    die("Log 4 not found\n");
}

echo "--- Log ID 4 Details ---\n";
echo "Event: " . $log->event . "\n";
echo "Status: " . $log->status . "\n";
echo "User ID: " . ($log->user_id ?? 'NULL') . "\n";
echo "Gateway Payment ID: " . $log->gateway_payment_id . "\n";
echo "Gateway Subscription ID: " . $log->gateway_subscription_id . "\n";

if ($log->user_id) {
    $user = User::find($log->user_id);
    echo "User Email: " . $user->email . "\n";
    echo "User Plan ID: " . ($user->plan_id ?? 'None') . "\n";
    echo "User Plan Start: " . ($user->plan_started_at ? $user->plan_started_at->toDateTimeString() : 'NULL') . "\n";
    echo "User Plan Expires: " . ($user->plan_expires_at ? $user->plan_expires_at->toDateTimeString() : 'NULL') . "\n";
}

$sub = Subscription::where('gateway_id', $log->gateway_subscription_id)->first();
if ($sub) {
    echo "Subscription Status: " . $sub->status . "\n";
    echo "Subscription User ID: " . $sub->user_id . "\n";
} else {
    echo "Subscription not found for gateway_id: " . $log->gateway_subscription_id . "\n";
}
