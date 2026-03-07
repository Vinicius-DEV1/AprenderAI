<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- HTTP REGISTER TEST ---\n";
try {
    $response = Http::withHeaders(['Accept' => 'application/json'])->post('http://webserver/api/v1/register', [
        'name' => 'Audit Registration Final Test',
        'email' => 'audit_final_test_' . time() . '@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    echo "STATUS: " . $response->status() . "\n";
    echo "BODY:\n" . $response->body() . "\n";
} catch (\Exception $e) {
    echo "EX DE REG: " . $e->getMessage() . "\n";
}
