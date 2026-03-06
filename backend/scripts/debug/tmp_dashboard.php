<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('email', 'admin@aprenderai.com')->first();
Auth::login($user);

$dashboardData = app(App\Http\Controllers\Api\V1\DashboardController::class)->index(request());
echo json_encode($dashboardData->getData(), JSON_PRETTY_PRINT);
