<?php
use App\Models\ApiKey;

$total = ApiKey::count();
$active = ApiKey::where('status', 'active')->count();
echo "api_keys_total={$total}\n";
echo "api_keys_active={$active}\n";

if ($total > 0) {
    $keys = ApiKey::select('id', 'provider', 'status', 'capabilities')->get();
    foreach ($keys as $k) {
        echo "  key_id={$k->id} provider={$k->provider} status={$k->status} capabilities=" . json_encode($k->capabilities) . "\n";
    }
} else {
    echo "NO API KEYS FOUND IN DB\n";
}

// Check hasActiveKey for essays
$hasEssays = ApiKey::getKeyForCapability(ApiKey::CAPABILITY_ESSAYS);
echo "has_essay_key=" . ($hasEssays ? "YES (id={$hasEssays->id})" : "NO") . "\n";
