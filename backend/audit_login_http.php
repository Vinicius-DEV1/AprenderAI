<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$response = Http::post('http://webserver/api/v1/login', [
    'email' => 'admin@aprenderai.com',
    'password' => 'password',
]);

echo "HTTP_STATUS: " . $response->status() . "\n";
echo "RESPONSE_BODY: " . $response->body() . "\n";
