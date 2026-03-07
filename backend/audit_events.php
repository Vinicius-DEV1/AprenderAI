<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Event;

echo "--- CHECKING LISTENERS FOR 'Registered' EVENT ---\n";
foreach (Event::getListeners(\Illuminate\Auth\Events\Registered::class) as $listener) {
    echo "Listener: " . (is_string($listener) ? $listener : (is_callable($listener) ? 'Closure/Callable' : get_class((object) $listener))) . "\n";
}

echo "\n--- CHECKING LISTENERS FOR 'Verified' EVENT ---\n";
foreach (Event::getListeners(\Illuminate\Auth\Events\Verified::class) as $listener) {
    echo "Listener: " . (is_string($listener) ? $listener : (is_callable($listener) ? 'Closure/Callable' : get_class((object) $listener))) . "\n";
}
