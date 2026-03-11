<?php

use App\Models\ApiKey;
use App\Models\ApiKeyVault;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vaultKeyName = 'vini2';
$vault = ApiKeyVault::where('nickname', $vaultKeyName)->first();

if (!$vault) {
    echo "No API Key Vault entry found for nickname '{$vaultKeyName}'.\n";
    $all = ApiKeyVault::all()->pluck('nickname')->toArray();
    echo "Available keys: " . implode(', ', $all) . "\n";
    exit(1);
}

// Ensure the first key is disabled or updated to give priority to the new one
ApiKey::query()->update(['is_primary' => false]);

// Create or update usage record
$key = ApiKey::updateOrCreate(
    ['vault_id' => $vault->id],
    [
        'provider' => $vault->provider,
        'is_active' => true,
        'status' => 'online',
        'capabilities' => ['general', 'triage', 'embedding', 'essays', 'search'],
        'is_primary' => true,
    ]
);

echo "ApiKey record #{$key->id} linked to Vault #{$vault->id} ({$vault->nickname}).\n";
echo "Capabilities: " . json_encode($key->capabilities) . "\n";
