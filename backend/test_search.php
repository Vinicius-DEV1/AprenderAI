<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

// Force enable vector search for this test
Config::set('xavier.vector_search_enabled', true);

// Auth as first user to avoid null errors
$user = User::first();
if (!$user) {
    die("No user found in database to authenticate for test.\n");
}
auth()->login($user);

$query = "questões sobre interpretação de texto e gramática";
echo "Searching for: '{$query}'...\n";

// We can call the controller method directly or use a mock request
$controller = app(\App\Http\Controllers\Api\QuestionController::class);
$request = new \Illuminate\Http\Request([
    'prompt' => $query
]);
$request->setUserResolver(fn() => $user);

$response = $controller->aiSearch($request);
$data = $response->getData(true);

echo "\n--- Results ---\n";
if (empty($data['questions'])) {
    echo "No questions found.\n";
} else {
    foreach ($data['questions'] as $q) {
        echo "[ID: {$q['id']}] [Score: " . ($q['search_score'] ?? 'N/A') . "] " . substr($q['statement'], 0, 100) . "...\n";
    }
}

echo "\n--- Debug Info ---\n";
echo "Total found: " . count($data['questions'] ?? []) . "\n";
if (isset($data['debug'])) {
    print_r($data['debug']);
}
