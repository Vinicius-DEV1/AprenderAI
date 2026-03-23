<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\EnemImportLog;

echo "=== LIMPEZA DE IMPORTAÇÃO ENEM ANTIGA ===\n\n";

// 1. Apagar todas as questões do tipo 'enem'
$count = Question::where('type', 'enem')->count();
echo "Encontradas {$count} questões do tipo 'enem' para limpar...\n";

// Usando forceDelete se o modelo usar SoftDeletes, ou delete() normal
// Para garantir limpeza completa das relações, é ideal deixar o DB/Eloquent cuidar do cascade, 
// ou deletar via query builder se for massivo.
try {
    DB::beginTransaction();

    // Como questions tem muitas relações (question_alternatives, question_images, logs), 
    // a exclusão em cascata deve cuidar disso através de onDelete('cascade') nas migrations.
    $deleted = Question::where('type', 'enem')->forceDelete();
    
    echo "Deletadas {$deleted} questões (e relações em cascata).\n";

    // 2. Apagar os logs antigos de importação para zerar o contador do dashboard Admin
    $logsDeleted = EnemImportLog::query()->delete();
    echo "Deletados {$logsDeleted} logs de importação (table enem_import_logs).\n";

    // 3. Limpar storage (opcional, vamos apenas garantir o DB limpo primeiro, imagens soltas não quebram o app)
    
    DB::commit();
    echo "\nLimpeza concluída com sucesso!\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERRO AO LIMPAR: " . $e->getMessage() . "\n";
}
