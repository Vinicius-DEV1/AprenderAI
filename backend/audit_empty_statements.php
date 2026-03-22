<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;

echo "=== DIAGNÓSTICO DE ENUNCIADOS VAZIOS ===\n\n";

$emptyQuestions = Question::where('type', 'enem')
    ->where(function($q) {
        $q->where('statement', '')
          ->orWhereNull('statement')
          ->orWhere('statement', 'LIKE', 'Sem enunciado disponível')
          ->orWhere('statement', 'LIKE', '**%**'); // Apenas negrito (sem contexto)
    })
    ->get();

if ($emptyQuestions->isEmpty()) {
    echo "✅ Nenhuma questão com enunciado vazio ou suspeito encontrada.\n";
} else {
    echo "❌ Foram encontradas " . $emptyQuestions->count() . " questões suspeitas:\n\n";
    foreach ($emptyQuestions as $q) {
        echo "ID: {$q->id} | Ano: {$q->year} | ExternalID: {$q->external_id}\n";
        echo "Statement Atual: " . substr($q->statement, 0, 100) . "...\n";
        echo "--------------------------------------------------\n";
    }
}
