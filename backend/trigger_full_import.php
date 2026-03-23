<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\ProcessEnemExamJob;
use App\Models\EnemImportLog;
use Illuminate\Support\Facades\Log;

echo "=== INICIANDO IMPORTAÇÃO MASSIVA (2009 - 2023) ===\n\n";

$years = range(2009, 2023);

foreach ($years as $year) {
    echo "Preparando importação para o ano: {$year}...\n";
    
    // Cria o log obrigatório para o Job
    $log = EnemImportLog::create([
        'year' => $year,
        'status' => 'processing',
        'inserted_count' => 0,
        'ignored_count' => 0,
        'error_count' => 0,
    ]);

    // O dispatch() envia isso pro driver configurado no .env (database, redis ou sync)
    // Se o driver for sync, ele vai executar um por um aqui segurando a janela.
    // Se for database, precisaremos garantir que tem um worker rodando.
    dispatch(new ProcessEnemExamJob($year, $log->id));
    
    echo "  -> Job adicionado na fila (Log ID: {$log->id}).\n";
}

echo "\nTodos os Jobs foram despachados.\n";
echo "Para verificar se rodaram de forma síncrona ou estão dependendo de worker:\n";
$firstLog = EnemImportLog::orderBy('id', 'desc')->first();
echo "Status do último job enfileirado: " . $firstLog->status . "\n\n";
