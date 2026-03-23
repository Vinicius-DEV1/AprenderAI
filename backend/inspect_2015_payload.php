<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EnemApiService;

$api = new EnemApiService();
$year = 2018;
$questions = $api->getExamQuestions($year, 50, 100); // 143 deve estar no terceiro lote
$qs = $questions['data'] ?? $questions['questions'] ?? $questions;
foreach ($qs as $q) {
    if (($q['index'] ?? 0) == 143) {
        echo "=== ACHADO 2018 INDEX 143 ===\n";
        print_r($q);
        exit;
    }
}

file_put_contents('/var/www/full_2015.json', json_encode($allQuestions, JSON_PRETTY_PRINT));
echo "Total baixado: " . count($allQuestions) . "\n";

$target = "Para resolver o problema";
foreach ($allQuestions as $q) {
    if (stripos(json_encode($q), $target) !== false) {
        echo "\n=== ACHADO! ===\n";
        print_r($q);
        exit;
    }
}
echo "Não achei '{$target}' em NENHUMA questão de 2015.\n";


$total = count($questions['data'] ?? $questions);
echo "Encontradas {$total} questões.\n";

$targetText = "aumento, em metros, no raio da cisterna";

foreach ($questions['data'] ?? $questions as $q) {
    $fullText = ($q['context'] ?? '') . ' ' . ($q['alternativesIntroduction'] ?? '');
    if (stripos($fullText, $targetText) !== false) {
        echo "\n=== QUESTÃO ENCONTRADA ===\n";
        echo "Index: " . ($q['index'] ?? 'N/A') . "\n";
        echo "PAYLOAD COMPLETO:\n";
        print_r($q);
        exit;
    }
}

echo "\nQuestão não encontrada com o texto: {$targetText}\n";
