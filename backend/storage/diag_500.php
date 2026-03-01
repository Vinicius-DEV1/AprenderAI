<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Http\Controllers\Api\EssayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Simulate the admin user
$user = User::where('email', 'admin@aprenderai.com')->first();
if (!$user) {
    echo "User not found\n";
    exit(1);
}

Auth::login($user);

echo "Testing getRule('enem')...\n";
try {
    $request = Request::create('/api/v1/essays/rule/enem', 'GET');
    $request->setUserResolver(fn() => $user);
    $controller = new EssayController();
    $response = $controller->getRule($request, 'enem');
    echo "SUCCESS: getRule generated correctly\n";
    echo "Data: " . json_encode($response->getData(), JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION in getRule:\n";
    echo "Class: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
}
