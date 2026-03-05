<?php

namespace App\Console\Commands;

use App\Models\ServerMetric;
use App\Services\ServerMetricService;
use Illuminate\Console\Command;

class CollectMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:collect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Coleta métricas do servidor (CPU, RAM, Rede) e salva no banco de dados.';

    /**
     * Execute the console command.
     */
    public function handle(ServerMetricService $service)
    {
        $this->info('Coletando métricas...');

        try {
            // O serviço collect() já extrai CPU, RAM e o delta da Velocidade de Rede internamente via cache.
            $metrics = $service->collect();

            // Salva no Banco apenas os valores necessários para os gráficos de histórico
            ServerMetric::create([
                'cpu_usage' => $metrics['cpu_usage'] ?? 0,
                'ram_usage' => $metrics['ram_usage'] ?? 0,
                'net_rx_speed' => $metrics['net_rx_speed'] ?? 0,
                'net_tx_speed' => $metrics['net_tx_speed'] ?? 0,
            ]);

            $this->info("Métricas salvas com sucesso.");
        } catch (\Exception $e) {
            $this->error("Erro ao coletar métricas: " . $e->getMessage());
        }

        // Limpeza Automática (Pruning) > 30 dias
        $deleted = ServerMetric::where('created_at', '<', now()->subDays(30))->delete();
        if ($deleted > 0) {
            $this->info("Pruning: {$deleted} registros antigos removidos.");
        }
    }
}
