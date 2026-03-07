<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

echo "DB_DATABASE: " . config('database.connections.mysql.database') . "\n";
echo "DB_HOST: " . config('database.connections.mysql.host') . "\n";

echo "--- 1. REPRODUCING LOGIN ERROR ---\n";
try {
    $response = Http::withHeaders(['Accept' => 'application/json'])
        ->post('http://webserver/api/v1/login', [
            'email' => 'admin@aprenderai.com',
            'password' => 'password'
        ]);
    echo "STATUS: " . $response->status() . "\n";
    echo "BODY: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "EX: " . $e->getMessage() . "\n";
}

echo "\n--- 2. DATABASE AUDIT (ADMIN USER) ---\n";
$user = User::where('email', 'admin@aprenderai.com')->first();
if ($user) {
    echo "USER FOUND: ID " . $user->id . "\n";
    echo "EMAIL: " . $user->email . "\n";
    echo "PASSWORD HASH: " . $user->password . "\n";
    echo "PASSWORD MATCH ('password'): " . (Hash::check('password', $user->password) ? "YES" : "NO") . "\n";
} else {
    echo "USER NOT FOUND: admin@aprenderai.com\n";
}

echo "\n--- 3. AUTH CONFIG AUDIT ---\n";
echo "AUTH_GUARD: " . config('auth.defaults.guard') . "\n";
echo "SESSION_DRIVER: " . config('session.driver') . "\n";
echo "SANCTUM_STATEFUL: " . implode(',', config('sanctum.stateful', [])) . "\n";
