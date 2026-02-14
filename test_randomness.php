<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CRIANDO 5 SIMULADOS PARA TESTAR ALEATORIEDADE ===\n\n";

$user = App\Models\User::first();
if (!$user) {
    echo "ERRO: Nenhum usuário encontrado.\n";
    exit(1);
}

$firstQuestions = [];

for ($i = 1; $i <= 5; $i++) {
    echo "Criando simulado {$i}...\n";

    // Simular request
    $type = 'enem';
    $total = 90;
    $distribution = ['português' => 45, 'matemática' => 45];

    // Criar simulação
    $simulation = App\Models\Simulation::create([
        'user_id' => $user->id,
        'type' => $type,
        'configuration' => [
            'questions' => $total,
            'subject_distribution' => $distribution,
            'include_essay' => false,
            'time_limit' => 16200,
        ],
        'status' => 'pending',
    ]);

    // Selecionar questões (com shuffle)
    $questions = collect();

    foreach ($distribution as $subject => $count) {
        $count = (int) $count;
        if ($count <= 0)
            continue;

        $selected = App\Models\Question::where('type', $type)
            ->where('subject', $subject)
            ->whereNotIn('id', $questions->pluck('id'))
            ->inRandomOrder()
            ->limit($count)
            ->get();

        $questions = $questions->merge($selected);
    }

    $missing = $total - $questions->count();
    if ($missing > 0) {
        $extra = App\Models\Question::where('type', $type)
            ->whereNotIn('id', $questions->pluck('id'))
            ->inRandomOrder()
            ->limit($missing)
            ->get();

        $questions = $questions->merge($extra);
    }

    // SHUFFLE (nova lógica)
    $questions = $questions->shuffle()->values()->take($total);

    // Criar answers
    $now = now();
    $rows = $questions->map(fn($q) => [
        'simulation_id' => $simulation->id,
        'question_id' => $q->id,
        'user_answer' => null,
        'is_correct' => false,
        'time_spent' => 0,
        'marked_for_review' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all();

    DB::table('simulation_answers')->insert($rows);

    // Pegar primeira questão
    $simulation->load('answers.question');
    $firstAnswer = $simulation->answers->first();

    if ($firstAnswer && $firstAnswer->question) {
        $firstQuestions[] = $firstAnswer->question->id;
        echo "  ✓ Simulação ID {$simulation->id} criada - Primeira questão: {$firstAnswer->question->id} ({$firstAnswer->question->subject})\n";
    }
}

echo "\n=== VERIFICAÇÃO DE ALEATORIEDADE ===\n";
echo "Primeiras questões: " . implode(', ', $firstQuestions) . "\n";

$unique = array_unique($firstQuestions);
echo "Questões únicas: " . count($unique) . " de " . count($firstQuestions) . "\n";

if (count($unique) >= 3) {
    echo "✓✓✓ SUCESSO! A ordem está variando corretamente.\n";
} else {
    echo "⚠️ ATENÇÃO: Ainda há pouca variação na primeira questão.\n";
}

echo "\nÚltimo simulado criado para teste: http://127.0.0.1:8000/simulations/" . $simulation->id . "\n";
