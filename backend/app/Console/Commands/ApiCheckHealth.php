<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ApiCheckHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:check-health';
    protected $description = 'Verifica a saúde das chaves de API ativas e atualiza status';

    public function handle(\App\Services\AI\AIService $aiService)
    {
        $this->info('Iniciando Teste de Saúde das APIs...');
        
        $keys = \App\Models\ApiKey::where('is_active', true)->get();

        foreach ($keys as $key) {
            $this->info("Testando Provedor: {$key->provider} (ID: {$key->id})");
            
            try {
                $result = $aiService->validateKey($key->provider, $key->decrypted_key);
                
                if ($result['is_valid']) {
                    $key->update([
                        'status' => 'online',
                        'last_health_check_at' => now(),
                    ]);
                    $this->line(" - <fg=green>ONLINE</>");
                } else {
                    $status = str_contains($result['error'] ?? '', '429') ? 'quota_exceeded' : 'offline';
                    $key->update([
                        'status' => $status,
                        'last_health_check_at' => now(),
                    ]);
                    $this->line(" - <fg=red>OFFLINE (" . ($result['error'] ?? 'Erro desconhecido') . ")</>");
                }
            } catch (\Exception $e) {
                $key->update([
                    'status' => 'offline',
                    'last_health_check_at' => now(),
                ]);
                $this->error(" - EXCEPTION: " . $e->getMessage());
            }
        }

        $this->info('Teste de Saúde finalizado.');
    }
}
