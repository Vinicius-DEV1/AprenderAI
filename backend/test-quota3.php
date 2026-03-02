<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::where('email', 'gratuito@aprenderai.com')->first();
if ($u) {
    echo "USER ID: {$u->id}\n";
    echo "PLAN ID: {$u->plan_id}\n";

    $qs = app(\App\Services\QuotaService::class);
    try {
        $qs->consumeQuota($u, 'essays');
        echo "Consumption OK\n";
    } catch (\Exception $e) {
        echo "ERRO CONSUMPTION: " . $e->getMessage() . "\n";
    }

    echo "USADO: " . $u->monthlyEssayUsed() . "\n";
}
