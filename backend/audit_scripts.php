<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Support\Facades\Log;

echo "--- 1. PROVA DE LOGIN ---\n";
$success = Auth::attempt(['email' => 'admin@aprenderai.com', 'password' => 'password']);
echo "Login admin@aprenderai.com: " . ($success ? "SUCESSO" : "FALHA") . "\n";
if ($success) {
    echo "User ID: " . Auth::id() . "\n";
}

echo "\n--- 2. AUDITORIA WELCOME EMAIL (Listeners) ---\n";
$listeners = Event::getListeners(Verified::class);
foreach ($listeners as $listener) {
    if (is_string($listener)) echo "  Listener: $listener\n";
    elseif ($listener instanceof Closure) echo "  Listener: Closure\n";
    else echo "  Listener Type: " . get_class((object)$listener) . "\n";
}

echo "\n--- 3. TESTE REAL: CRIAÇÃO DE USUÁRIO E DISPARO ---\n";
$email = 'test_audit_' . time() . '@example.com';
$testUser = User::create([
    'name' => 'Audit User',
    'email' => $email,
    'password' => bcrypt('password'),
]);
echo "Usuário de teste criado: $email (ID: {$testUser->id})\n";

Log::info("AUDIT TEST: Firing Verified event for $email");
event(new Verified($testUser));
echo "Evento Verified disparado. Verifique os logs do Laravel para confirmar o envio.\n";
