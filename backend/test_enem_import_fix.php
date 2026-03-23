<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EnemImportService;

echo "=== TESTE DE VALIDAÇÃO: ENEM IMPORT SERVICE FIX ===\n\n";

$service = new EnemImportService();

// 1. Teste de questão com context vazio (ex: ENEM 2023)
$apiQuestion2023 = [
    'year' => 2023,
    'index' => 2,
    'context' => '',
    'alternativesIntroduction' => 'Este es el texto introductorio que sirve como el enunciado principal de la pregunta.',
    'files' => [],
    'discipline' => 'linguagens',
    'language' => 'espanhol',
    'alternatives' => [
        ['letter' => 'A', 'text' => 'Alternativa A', 'file' => null, 'isCorrect' => true],
        ['letter' => 'B', 'text' => 'Alternativa B', 'file' => null, 'isCorrect' => false]
    ]
];

echo "Testando Questão 2023 (context vazio)...\n";
$result2023 = $service->processQuestion($apiQuestion2023);
if ($result2023['status'] === 'success') {
    $q = $result2023['question'];
    echo "SUCESSO: Questão criada ID: {$q->id}\n";
    echo "STATEMENT RESULTANTE:\n[{$q->statement}]\n";
    echo "EXTERNAL_ID: {$q->external_id}\n\n";
} else {
    echo "FALHA: " . print_r($result2023, true) . "\n\n";
}

// 2. Teste de questão de imagem pura (ex: ENEM 2010)
$apiQuestion2010 = [
    'year' => 2010,
    'index' => 999, // Fake index to avoid collision if run multiple times
    'context' => 'Aqui está um enunciado normal para testar imagens nas alternativas.',
    'alternativesIntroduction' => 'Assinale a figura correta:',
    'files' => [],
    'discipline' => 'matematica',
    'alternatives' => [
        ['letter' => 'A', 'text' => '', 'file' => 'https://via.placeholder.com/150/0000FF/808080?Text=ImgA', 'isCorrect' => true],
        ['letter' => 'B', 'text' => '', 'file' => 'https://via.placeholder.com/150/FF0000/FFFFFF?Text=ImgB', 'isCorrect' => false]
    ]
];

echo "Testando Questão 2010 (alternativas image-only)...\n";
$result2010 = $service->processQuestion($apiQuestion2010);
if ($result2010['status'] === 'success') {
    $q = $result2010['question'];
    echo "SUCESSO: Questão criada ID: {$q->id}\n";
    echo "STATEMENT RESULTANTE:\n[{$q->statement}]\n";
    $alts = \App\Models\QuestionAlternative::where('question_id', $q->id)->get();
    echo "ALTERNATIVAS:\n";
    foreach ($alts as $alt) {
        echo "  [{$alt->label}] content: [{$alt->content}] | image_path: {$alt->image_path}\n";
    }
} else {
    echo "FALHA: " . print_r($result2010, true) . "\n\n";
}

echo "\nFIM DOS TESTES.\n";
