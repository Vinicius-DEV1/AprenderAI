<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- HTTP LOGIN TEST ---\n";
try {
    $response = Http::post('http://webserver/api/v1/login', [
        'email' => 'admin@aprenderai.com',
        'password' => 'password'
    ]);
    echo "STATUS: " . $response->status() . "\n";
    echo "BODY: " . substr($response->body(), 0, 500) . "\n";
} catch (\Exception $e) {
    echo "EX DE LOGIN: " . $e->getMessage() . "\n";
}

echo "--- HTTP REGISTER TEST ---\n";
try {
    $response = Http::post('http://webserver/api/v1/register', [
        'name' => 'Audit Registration Final Test',
        'email' => 'audit_final_test_' . time() . '@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    echo "STATUS: " . $response->status() . "\n";
    echo "BODY: " . substr($response->body(), 0, 500) . "\n";
} catch (\Exception $e) {
    echo "EX DE REG: " . $e->getMessage() . "\n";
}
