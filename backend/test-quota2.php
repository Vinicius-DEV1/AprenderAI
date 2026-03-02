<?php
$u = \App\Models\User::where('email', 'gratuito@aprenderai.com')->first();
if ($u) {
    echo "USER ID: {$u->id}\n";
    echo "PLAN ID: {$u->plan_id}\n";
    echo "PLAN LOADED: " . ($u->plan ? $u->plan->name : 'null') . "\n";

    // Explicit load
    $u->load('plan');
    echo "PLAN (after load): " . ($u->plan ? $u->plan->name : 'null') . "\n";

    // Test QuotaService
    $qs = app(\App\Services\QuotaService::class);
    try {
        $qs->consumeQuota($u, 'essays');
        echo "Consumption OK\n";
    } catch (\Exception $e) {
        echo "ERRO CONSUMPTION: " . $e->getMessage() . "\n";
    }
}
