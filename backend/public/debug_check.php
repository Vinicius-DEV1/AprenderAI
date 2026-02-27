<?php
// public/debug_check.php

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<pre>";
echo "<h1>DEBUG REPORT</h1>";

// 1. Check API Keys
echo "<h2>1. API Keys (DB)</h2>";
try {
    $keys = \App\Models\ApiKey::all();
    if ($keys->isEmpty()) {
        echo "❌ Nenhuma chave encontrada no banco de dados.\n";
    }
    else {
        foreach ($keys as $key) {
            echo "ID: {$key->id} | Provider: {$key->provider} | Active: " . ($key->is_active ? '✅' : '❌') . " | Status: {$key->status}\n";
            // Auto-fix for debug
            if ($key->is_active && $key->status !== 'online') {
                $key->update(['status' => 'online']);
                echo "   -> 🛠️ FIX APPLIED: Status updated to 'online'.\n";
            }
        }
    }
}
catch (\Exception $e) {
    echo "❌ Erro ao ler banco: " . $e->getMessage() . "\n";
}

// 2. Restart Queue
echo "\n<h2>2. Queue Worker</h2>";
try {
    \Illuminate\Support\Facades\Artisan::call('queue:restart');
    echo "✅ Comando 'queue:restart' enviado com sucesso.\n";
}
catch (\Exception $e) {
    echo "❌ Falha ao reiniciar fila: " . $e->getMessage() . "\n";
}

// 3. Check Cache/Config match
echo "\n<h2>3. Environment</h2>";
echo "APP_ENV: " . config('app.env') . "\n";
echo "AIService Provider count: " . count(config('services.ai.providers') ?? []) . " (Should be irrelevant if hardcoded in class)\n";

echo "</pre>";
