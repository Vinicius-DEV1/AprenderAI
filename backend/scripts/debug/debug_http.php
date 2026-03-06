<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();

// Create a fake API token for testing
$token = $user->createToken('debug-test')->plainTextToken;

$request = \Illuminate\Http\Request::create(
    '/api/v1/study-plan',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CONTENT_TYPE' => 'application/json',
    ],
    json_encode([
        'hours_per_day' => 4,
        'exam_type' => 'enem',
        'exam_name' => null,
        'exam_date' => null,
    ])
);
$request->headers->set('Accept', 'application/json');
$request->headers->set('Content-Type', 'application/json');

$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n";

$kernel->terminate($request, $response);

// Clean up token
$user->tokens()->where('name', 'debug-test')->delete();
