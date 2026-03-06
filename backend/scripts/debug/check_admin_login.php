<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::where('email', 'admin@aprenderai.com')->first();
if ($user) {
    echo "User found: " . $user->email . "\n";
    echo "Hash match Aprova@123: " . (Hash::check('Aprova@123', $user->password) ? 'YES' : 'NO') . "\n";
} else {
    echo "User NOT FOUND.\n";
}
