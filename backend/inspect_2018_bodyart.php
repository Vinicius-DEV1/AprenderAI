<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EnemApiService;

$api = new EnemApiService();
$year = 2018;

echo "Buscando todas as questões de {$year}...\n";
$allQs = [];
for ($offset = 0; $offset < 200; $offset += 50) {
    echo "Offset {$offset}...\n";
    $batch = $api->getExamQuestions($year, 50, $offset);
    $qs = $batch['data'] ?? $batch['questions'] ?? $batch;
    if (empty($qs)) break;
    $allQs = array_merge($allQs, $qs);
}

$target = "body art";
foreach ($allQs as $q) {
    if (stripos(json_encode($q), $target) !== false) {
        echo "\n=== ACHADO! ===\n";
        echo "Index: " . ($q['index'] ?? 'N/A') . "\n";
        print_r($q);
        exit;
    }
}
echo "Não achei '{$target}' em {$year}.\n";
