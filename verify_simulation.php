<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sim = App\Models\Simulation::latest()->first();

if (!$sim) {
    echo "Nenhuma simulação encontrada.\n";
    exit(1);
}

echo "=== VERIFICAÇÃO DO ÚLTIMO SIMULADO ===\n\n";
echo "Simulation ID: {$sim->id}\n";
echo "Type: {$sim->type}\n";
echo "Status: {$sim->status}\n";
echo "Total questions (config): {$sim->configuration['questions']}\n";

$answersCount = $sim->answers()->count();
echo "Answers count: {$answersCount}\n\n";

echo "Match: " . ($answersCount === $sim->configuration['questions'] ? 'YES ✓✓✓' : 'NO ✗✗✗') . "\n\n";

if ($answersCount === $sim->configuration['questions']) {
    echo "URL para testar: http://127.0.0.1:8000/simulations/{$sim->id}\n";
} else {
    echo "ERRO: O simulado tem {$answersCount} answers mas deveria ter {$sim->configuration['questions']}!\n";
}
