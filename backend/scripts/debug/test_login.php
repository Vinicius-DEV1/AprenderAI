<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
// Simula a requisição de login
$request = Request::create('/api/v1/login', 'POST', [
    'email' => 'admin@aprenderai.com',
    'password' => 'Aprova@123'
]);
$request->headers->set('Accept', 'application/json');

Log::info('Simulating login for admin test...');
$response = app()->handle($request);
echo "\n--- HTTP RESPONSE STATUS ---\n";
echo "STATUS CODE: " . $response->getStatusCode() . "\n";
echo "RESPONSE CONTENT: " . json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT) . "\n";
echo "----------------------------\n";
