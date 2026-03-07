<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- REGISTRATION TEST ---\n";
$email = 'audit_reg_' . time() . '@test.com';
$response = Http::post('http://webserver/api/v1/register', [
    'name' => 'Audit Registration Test',
    'email' => $email,
    'password' => 'password',
    'password_confirmation' => 'password',
]);

echo "HTTP_STATUS: " . $response->status() . "\n";
echo "RESPONSE_BODY: " . $response->body() . "\n";

