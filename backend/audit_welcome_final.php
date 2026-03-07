<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Auth\Events\Verified;

echo "--- 4. TESTE REAL DO WELCOME EMAIL ---\n";
$email = 'audit_final_' . time() . '@test.com';
$user = User::create([
    'name' => 'Audit Final',
    'email' => $email,
    'password' => bcrypt('password'),
]);
echo "USER_CREATED: " . $email . " (ID: " . $user->id . ")\n";

echo "FIRING_EVENT: Verified (Simulating email verification link click)\n";
event(new Verified($user));
echo "EVENT_FIRED\n";
