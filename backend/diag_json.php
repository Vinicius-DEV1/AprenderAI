<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->boot();

echo "USER_EMAIL:" . \App\Models\User::where('is_admin', 1)->first()?->email . "\n";
$data = app(\App\Http\Controllers\Api\Admin\ApiKeyController::class)->index()->getData();
echo "JSON_START\n";
echo json_encode($data);
echo "\nJSON_END\n";
