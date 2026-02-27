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

        // 1. Snapshot Inicial de Rede
        $netStart = $service->getNetworkStats();
        
        // 2. Aguarda 1 segundo para delta
        sleep(1);
        
        // 3. Snapshot Final e CPU/RAM
        $netEnd = $service->getNetworkStats();
        $cpu = $service->getCpuUsage();
        $ram = $service->getRamUsage();

        // 4. Calcula Velocidade (Bytes/s)
        $rxSpeed = max(0, $netEnd['rx'] - $netStart['rx']);
        $txSpeed = max(0, $netEnd['tx'] - $netStart['tx']);

        // Se o contador reiniciou ou houve erro, ignora valor negativo
        if ($rxSpeed < 0) $rxSpeed = 0;
        if ($txSpeed < 0) $txSpeed = 0;

        // 5. Salva no Banco
        ServerMetric::create([
            'cpu_usage' => $cpu,
            'ram_usage' => $ram,
            'net_rx_speed' => $rxSpeed,
            'net_tx_speed' => $txSpeed,
        ]);

        $this->info("Métricas salvas: CPU: {$cpu}%, RAM: {$ram}%, RX: {$rxSpeed} B/s, TX: {$txSpeed} B/s");

        // 6. Limpeza Automática (Pruning) > 30 dias
        $deleted = ServerMetric::where('created_at', '<', now()->subDays(30))->delete();
        if ($deleted > 0) {
            $this->info("Pruning: {$deleted} registros antigos removidos.");
        }
    }
}
