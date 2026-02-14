<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Pegar último simulado
$sim = App\Models\Simulation::latest()->first();

if (!$sim) {
    echo "Nenhuma simulação encontrada.\n";
    exit(1);
}

echo "=== DEBUG SIMULAÇÃO ID {$sim->id} ===\n\n";

// Verificar answers
$answersCount = $sim->answers()->count();
echo "Answers count: {$answersCount}\n";

// Verificar eager load
$sim->load(['answers.question']);
echo "Answers loaded: " . $sim->answers->count() . "\n";

// Verificar se questions estão OK
$nullQuestions = 0;
$validQuestions = 0;

foreach ($sim->answers as $index => $answer) {
    if (!$answer->question) {
        $nullQuestions++;
        echo "✗ Answer ID {$answer->id} (question_id: {$answer->question_id}) - question is NULL\n";
    } else {
        $validQuestions++;
        if ($index === 0) {
            echo "✓ First answer (ID {$answer->id}) - question ID: {$answer->question->id}, subject: {$answer->question->subject}\n";
        }
    }
}

echo "\nValid questions: {$validQuestions}\n";
echo "Null questions: {$nullQuestions}\n";

if ($nullQuestions > 0) {
    echo "\n⚠️ PROBLEMA: Algumas questions estão NULL!\n";
    echo "Verificando IDs inválidos...\n";

    $invalidIds = [];
    foreach ($sim->answers as $answer) {
        if (!$answer->question) {
            $exists = DB::table('questions')->where('id', $answer->question_id)->exists();
            if (!$exists) {
                $invalidIds[] = $answer->question_id;
                echo "✗ Question ID {$answer->question_id} não existe na tabela questions\n";
            }
        }
    }
}

// Verificar primeiro question_id de múltiplos simulados
echo "\n=== VERIFICANDO ALEATORIEDADE ===\n";
$simulations = App\Models\Simulation::with('answers')->latest()->take(5)->get();

foreach ($simulations as $s) {
    $firstAnswer = $s->answers->first();
    if ($firstAnswer && $firstAnswer->question) {
        echo "Sim ID {$s->id}: primeira questão ID {$firstAnswer->question->id} - {$firstAnswer->question->subject}\n";
    } else {
        echo "Sim ID {$s->id}: SEM primeira questão\n";
    }
}
