<?php

use App\Models\User;
use App\Models\Favorite;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- FAVORITES DIAGNOSTIC ---\n";

$totalFavorites = Favorite::count();
echo "Total Favorites: $totalFavorites\n";

$usersWithFavorites = Favorite::select('user_id')->distinct()->pluck('user_id');
echo "Users with Favorites: " . $usersWithFavorites->implode(', ') . "\n";

foreach ($usersWithFavorites as $userId) {
    $user = User::find($userId);
    if ($user) {
        echo "User ID $userId ({$user->email}): " . Favorite::where('user_id', $userId)->count() . " favorites\n";
    } else {
        echo "User ID $userId (ORPHANED): " . Favorite::where('user_id', $userId)->count() . " favorites\n";
    }
}

echo "--- END DIAGNOSTIC ---\n";
