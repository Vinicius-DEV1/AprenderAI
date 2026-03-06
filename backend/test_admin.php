<?php
echo "APP_KEY_CONFIG:" . config('app.key') . "\n";
echo "APP_KEY_ENV:" . env('APP_KEY') . "\n";

$v = \App\Models\ApiKeyVault::find(1);
if ($v) {
    echo "VAULT_KEY_STORED:" . $v->getRawOriginal('key') . "\n";
    try {
        $dec = $v->decrypted_key;
        echo "VAULT_DECRYPT: OK\n";
    } catch (\Exception $e) {
        echo "VAULT_DECRYPT: FAIL - " . $e->getMessage() . "\n";
    }
}
