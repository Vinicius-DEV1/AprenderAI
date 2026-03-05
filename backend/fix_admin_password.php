<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

try {
    $user = User::where('email', 'admin@aprenderai.com')->first();
    if ($user) {
        $user->password = Hash::make('Aprova@123');
        $user->save();
        echo "SUCCESS: Admin password updated to Aprova@123.\n";
    } else {
        echo "ERROR: Admin not found.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
