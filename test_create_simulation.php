<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Criar um usuário de teste (ou usar existente)
$user = App\Models\User::first();
if (!$user) {
    echo "ERRO: Nenhum usuário encontrado no banco.\n";
    exit(1);
}

echo "Usuário: {$user->name} (ID: {$user->id})\n\n";

// Simular criação de simulado ENEM 90 questões
$type = 'enem';
$total = 90;
$distribution = ['português' => 45, 'matemática' => 45];

echo "Criando simulado ENEM com {$total} questões...\n";
echo "Distribuição: " . json_encode($distribution) . "\n\n";

// 1) Validar soma
$sum = collect($distribution)->sum(fn($v) => (int) $v);
if ($sum !== $total) {
    echo "ERRO: A soma da distribuição ({$sum}) deve ser igual ao total ({$total}).\n";
    exit(1);
}
echo "✓ Validação de soma: OK\n";

// 2) Criar simulação
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

echo "✓ Simulação criada (ID: {$simulation->id})\n";

// 3) Selecionar questões
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

// Completa se faltou
$missing = $total - $questions->count();
if ($missing > 0) {
    $extra = App\Models\Question::where('type', $type)
        ->whereNotIn('id', $questions->pluck('id'))
        ->inRandomOrder()
        ->limit($missing)
        ->get();

    $questions = $questions->merge($extra);
}

echo "✓ Questões selecionadas: {$questions->count()}\n";

// 4) Garantir que montou exatamente o total
if ($questions->count() !== $total) {
    echo "ERRO: Banco insuficiente. Foram selecionadas {$questions->count()} de {$total}.\n";
    $simulation->delete();
    echo "✓ Simulação deletada.\n";
    exit(1);
}

// 5) Criar answers em lote
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

$answersCount = DB::table('simulation_answers')
    ->where('simulation_id', $simulation->id)
    ->count();

echo "✓ Answers criadas: {$answersCount}\n\n";

// Verificação final
echo "=== VERIFICAÇÃO FINAL ===\n";
echo "Total esperado: {$total}\n";
echo "Total de answers: {$answersCount}\n";
echo "Match: " . ($answersCount === $total ? 'YES ✓✓✓' : 'NO ✗✗✗') . "\n\n";

if ($answersCount === $total) {
    echo "✓✓✓ SUCESSO! Simulado criado corretamente.\n";
    echo "URL para testar: http://127.0.0.1:8000/simulations/{$simulation->id}\n";
} else {
    echo "✗✗✗ FALHA! O simulado não tem o número correto de questões.\n";
    $simulation->delete();
}
