<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
\Illuminate\Support\Facades\Auth::login($user);

$query = "questões de direito constitucional menos informatica";
$request = \Illuminate\Http\Request::create('/api/v1/admin/semantic/test-search', 'POST', [
    'prompt' => $query
]);
$request->setUserResolver(function () use ($user) { return $user; });

$controller = app(\App\Http\Controllers\Api\Admin\SemanticDashboardController::class);
$response = $controller->testSearch($request, app(\App\Services\AI\SemanticCacheService::class));

echo $response->getContent();
