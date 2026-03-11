<?php

use App\Models\ApiKey;
use App\Models\ApiKeyVault;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vault = ApiKeyVault::first();

if (!$vault) {
    echo "No API Key Vault entry found. Key must be registered in the vault first.\n";
    exit(1);
}

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
