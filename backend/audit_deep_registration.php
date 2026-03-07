<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

echo "--- RUNTIME ENVIRONMENT ---\n";
echo "QUEUE_CONNECTION=" . env('QUEUE_CONNECTION', 'null') . "\n";
echo "MAIL_MAILER=" . env('MAIL_MAILER', 'null') . "\n";
echo "MAIL_HOST=" . env('MAIL_HOST', 'null') . "\n";
echo "MAIL_USERNAME=" . env('MAIL_USERNAME', 'null') . "\n";

echo "\n--- EVENT BINDINGS (Registered) ---\n";
foreach (Event::getListeners(Registered::class) as $listener) {
    if (is_string($listener)) {
        echo "Listener: $listener\n";
    } elseif (is_callable($listener)) {
        echo "Listener: Closure/Callable\n";
    } else {
        echo "Listener: " . get_class((object) $listener) . "\n";
    }
}

echo "\n--- DEEP REGISTRATION TEST ---\n";
$email = 'audit_deep_' . time() . '@test.com';

try {
    $response = Http::post('http://webserver/api/v1/register', [
        'name' => 'Audit Registration Final Test',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    echo "HTTP_STATUS: " . $response->status() . "\n";

    $user = User::where('email', $email)->first();
    if ($user) {
        echo "USER_CREATED: ID " . $user->id . "\n";
    } else {
        echo "USER NOT FOUND AFTER REGISTRATION\n";
        echo "RESPONSE_BODY: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "HTTP REQUEST EXCEPTION: " . $e->getMessage() . "\n";
}
