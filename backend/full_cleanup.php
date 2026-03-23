<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Question;
use App\Models\EnemImportLog;

echo "=== LIMPEZA TOTAL PARA REINÍCIO (Delay 0.2s) ===\n\n";

try {
    DB::beginTransaction();

    // 1. Limpar Jobs pendentes na fila
    if (Schema::hasTable('jobs')) {
        $jobsCount = DB::table('jobs')->count();
        DB::table('jobs')->truncate();
        echo "Limpados {$jobsCount} jobs da tabela 'jobs'.\n";
    } else {
        echo "Tabela 'jobs' não encontrada, assumindo fila vazia ou outro driver.\n";
    }

    // 2. Apagar questões ENEM
    $qCount = Question::where('type', 'enem')->count();
    Question::where('type', 'enem')->forceDelete();
    echo "Deletadas {$qCount} questões ENEM.\n";

    // 3. Apagar logs
    $lCount = EnemImportLog::query()->delete();
    echo "Deletados {$lCount} logs de importação.\n";

    DB::commit();
    echo "\nLimpeza concluída. Pronto para o 'trigger_full_import.php'.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERRO: " . $e->getMessage() . "\n";
}

use Illuminate\Support\Facades\Schema; // Added missing import
