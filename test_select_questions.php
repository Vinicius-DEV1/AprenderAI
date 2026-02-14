<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simular o método selectQuestions
$type = 'enem';
$total = 90;
$distribution = ['português' => 45, 'matemática' => 45];

$questions = collect();

// 1) Por subject
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

    echo "Subject: $subject, Requested: $count, Found: " . $selected->count() . PHP_EOL;
    $questions = $questions->merge($selected);
}

echo "\nTotal collected after distribution: " . $questions->count() . PHP_EOL;

// 2) Completa se faltou
$missing = $total - $questions->count();
if ($missing > 0) {
    echo "Missing: $missing, trying to fill with any questions from type=$type..." . PHP_EOL;

    $extra = App\Models\Question::where('type', $type)
        ->whereNotIn('id', $questions->pluck('id'))
        ->inRandomOrder()
        ->limit($missing)
        ->get();

    echo "Extra found: " . $extra->count() . PHP_EOL;
    $questions = $questions->merge($extra);
}

echo "\nFinal total: " . $questions->count() . PHP_EOL;
echo "Expected: $total" . PHP_EOL;
echo "Match: " . ($questions->count() === $total ? 'YES ✓' : 'NO ✗') . PHP_EOL;

// Verificar IDs únicos
$uniqueIds = $questions->pluck('id')->unique();
echo "\nUnique IDs: " . $uniqueIds->count() . PHP_EOL;
echo "Has duplicates: " . ($uniqueIds->count() !== $questions->count() ? 'YES ✗' : 'NO ✓') . PHP_EOL;
