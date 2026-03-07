<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$success = Illuminate\Support\Facades\Auth::attempt(['email' => 'admin@aprenderai.com', 'password' => 'password']);
echo "HTTP_STATUS|200\n";
echo "LOGIN_SUCCESS|" . ($success ? "true" : "false") . "\n";
