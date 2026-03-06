<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$key = \App\Models\ApiKey::find(1);
if ($key) {
    $key->update(['status' => 'online', 'last_error_message' => null]);
    \App\Models\ApiKey::clearBlacklist(1);
    echo "SUCCESS: Key ID 1 is online.\n";
} else {
    echo "ERROR: Key not found.\n";
}
