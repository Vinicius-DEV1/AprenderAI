<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $db = Illuminate\Support\Facades\DB::class;

    // Todos subjects
    $all = $db::table('subjects')->orderBy('name')->pluck('name')->toArray();

    // Subjects ENem
    $enem = \App\Models\Subject::whereHas('questions', fn($q) => $q->where('type', 'enem'))->orderBy('name')->pluck('name')->toArray();

    // Subjects Concurso
    $concurso = \App\Models\Subject::whereHas('questions', fn($q) => $q->where('type', 'concurso'))->orderBy('name')->pluck('name')->toArray();

    echo "\n=== TODAS AS MATÉRIAS ===\n" . implode(", ", $all);
    echo "\n\n=== MATÉRIAS ENEM ===\n" . implode(", ", $enem);
    echo "\n\n=== MATÉRIAS CONCURSO ===\n" . implode(", ", $concurso);
    echo "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
