<?php
$u = \App\Models\User::where('email', 'gratuito@aprenderai.com')->first();
if ($u) {
    try {
        $u->incrementEssayUsage();
        echo "USADO (getUsage): " . $u->monthlyEssayUsed() . "\n";
        $cycle = \App\Models\SubscriptionCycle::where('user_id', $u->id)->first();
        echo "CYCLE ID: " . ($cycle ? $cycle->id : 'none') . "\n";
        $ledgers = \App\Models\UsageLedger::where('subscription_cycle_id', $cycle->id ?? 0)->get();
        echo "LEDGERS COUNT: " . $ledgers->count() . "\n";
        foreach ($ledgers as $l)
            echo "- " . $l->feature_name . ": " . $l->amount . "\n";
    } catch (\Exception $e) {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
} else {
    echo "Usuário não encontrado\n";
}
