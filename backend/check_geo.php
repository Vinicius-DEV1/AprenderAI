<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $geoSubject = \App\Models\Subject::where('name', 'GEOGRAFIA')->first();
    if ($geoSubject) {
        $countEnem = \App\Models\Question::published()
            ->where('tipo_questao', '!=', 'Redação')
            ->where('type', 'enem')
            ->whereHas('subjects', fn($q) => $q->where('subjects.id', $geoSubject->id))
            ->count();

        $countConcurso = \App\Models\Question::published()
            ->where('tipo_questao', '!=', 'Redação')
            ->where('type', 'concurso')
            ->whereHas('subjects', fn($q) => $q->where('subjects.id', $geoSubject->id))
            ->count();

        echo "Geografia (ID: {$geoSubject->id})\n";
        echo "Questões ENEM: {$countEnem}\n";
        echo "Questões Concurso: {$countConcurso}\n";
    } else {
        echo "Matéria GEOGRAFIA não encontrada.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
