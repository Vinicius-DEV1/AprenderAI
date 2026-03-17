<?php

use App\Models\Question;
use App\Models\QuestionImportItem;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- Script de Limpeza de Questões Órfãs ---\n";

$orphansQuery = Question::whereDoesntHave('importItem');
$orphanCount = $orphansQuery->count();

if ($orphanCount === 0) {
    echo "Nenhuma questão órfã encontrada. O banco está limpo!\n";
    exit;
}

echo "Foram encontradas {$orphanCount} questões órfãs (sem lote de importação).\n";
echo "Limpando questões e dependências...\n";

$bar = null;
if (isset($argv) && in_array('--progress', $argv)) {
    echo "Iniciando exclusão...\n";
}

$orphansQuery->chunk(100, function ($questions) {
    foreach ($questions as $question) {
        // Soft delete ou force delete? Como são órfãs de erro, vamos de force delete
        // para limpar o external_id e permitir re-importação.
        $question->forceDelete();
    }
    echo ".";
});

echo "\nLimpeza concluída! {$orphanCount} questões removidas.\n";
echo "Agora você pode tentar importar o arquivo novamente.\n";
