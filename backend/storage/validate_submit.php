<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Essay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Simulate the admin user
$user = User::where('email', 'admin@aprenderai.com')->first();
Auth::login($user);

echo "Creating a test essay draft...\n";
$essay = Essay::create([
    'user_id' => $user->id,
    'type' => 'enem',
    'title' => 'Validation Test',
    'content' => 'Lorem ipsum dolor sit amet...',
    'status' => 'draft',
    'input_type' => 'text'
]);

echo "Created Essay ID: " . $essay->id . "\n";

echo "Testing Submission POST /api/v1/essays/" . $essay->id . "/submit...\n";
try {
    $request = Request::create("/api/v1/essays/{$essay->id}/submit", 'POST', [
        'content' => 'Conteúdo final da redação para teste.',
    ]);
    $request->setUserResolver(fn() => $user);

    // Using the controller directly to simulate the submissão
    $controller = new \App\Http\Controllers\Api\EssayController();
    $response = $controller->submitEssay($request, $essay->id);

    echo "SUCCESS: Submit returned status " . $response->getStatusCode() . "\n";
    echo "Data: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION in submitEssay:\n";
    echo "Class: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
