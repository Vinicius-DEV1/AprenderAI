<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = $app->make('App\Models\User')->where('email', 'admin@aprenderai.com')->first();
if (!$user) {
    echo "User admin@aprenderai.com not found\n";
    exit(1);
}

$request = call_user_func(['Illuminate\Http\Request', 'create'], '/api/essays', 'GET');
$request->setUserResolver(fn() => $user);
$controller = $app->make('App\Http\Controllers\Api\EssayController');

echo "Testing Index...\n";
try {
    $response = $controller->index($request);
    echo "Success: " . json_encode($response->getData()) . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
