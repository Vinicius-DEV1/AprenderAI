<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ULTIMOS 5 SIMULADOS CRIADOS ===\n\n";

$sims = App\Models\Simulation::with('answers.question')->latest()->take(5)->get();

$firstIds = [];

foreach ($sims as $s) {
    $first = $s->answers->first();
    if ($first && $first->question) {
        $firstIds[] = $first->question->id;
        echo "Sim ID {$s->id}: primeira Q{$first->question->id} ({$first->question->subject})\n";
    }
}

echo "\n=== ANÁLISE DE ALEATORIEDADE ===\n";
echo "IDs das primeiras questões: " . implode(', ', $firstIds) . "\n";
$unique = array_unique($firstIds);
echo "IDs únicos: " . count($unique) . " de " . count($firstIds) . "\n";

if (count($unique) >= 3) {
    echo "\n✓✓✓ SUCESSO! Aleatoriedade funcionando.\n";
} else {
    echo "\n⚠️ AVISO: Pouca variação detectada.\n";
}
