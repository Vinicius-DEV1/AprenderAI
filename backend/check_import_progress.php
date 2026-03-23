<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== PROGRESSO DA NOVA IMPORTAÇÃO ENEM ===\n\n";

$logs = DB::table('enem_import_logs')
    ->select('id','year','status','inserted_count','ignored_count','error_count', 'updated_at')
    ->orderBy('id', 'asc') // Mostra pela ordem dos anos q foram despachados
    ->get();

$totalInseridas = 0;
foreach ($logs as $log) {
    echo "Ano {$log->year} | Status: {$log->status} | Inseridas: {$log->inserted_count} | Ignoradas: {$log->ignored_count} | Erros: {$log->error_count} | Modificado: {$log->updated_at}\n";
    $totalInseridas += $log->inserted_count;
}

echo "\nTotal Geral Inseridas até agora: {$totalInseridas}\n\n";

// Verificar se as imagens estão certas agora nas alternativas!
$doubled = DB::table('question_alternatives')
    ->whereRaw("content LIKE '%![%'")
    ->whereNotNull('image_path')
    ->count();
echo "-> Alternativas com bug de double-embedding (markdown + path): {$doubled} (esperado: 0)\n";

$emptyCtxCount = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw("TRIM(statement) = '' OR statement IS NULL")
    ->count();
echo "-> Questões com enunciado vazio: {$emptyCtxCount} (esperado: 0)\n";
