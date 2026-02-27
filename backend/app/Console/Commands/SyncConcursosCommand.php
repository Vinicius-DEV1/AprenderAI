<?php

namespace App\Console\Commands;

use App\Services\ConcursoSyncService;
use Illuminate\Console\Command;

/**
 * Sincroniza concursos públicos a partir da API externa.
 *
 * Para agendar via Kernel (sem alterar o arquivo de scheduler):
 *   $schedule->command('concursos:sync')->daily();
 * (Adicione isso ao app/Console/Kernel.php -> schedule() caso deseje agendar.)
 */
class SyncConcursosCommand extends Command
{
    protected $signature = 'concursos:sync';

    protected $description = 'Sincroniza concursos públicos a partir da API externa';

    public function handle(ConcursoSyncService $service): int
    {
        $ufs = [
            'AC',
            'AL',
            'AM',
            'AP',
            'BA',
            'CE',
            'DF',
            'ES',
            'GO',
            'MA',
            'MG',
            'MS',
            'MT',
            'PA',
            'PB',
            'PE',
            'PI',
            'PR',
            'RJ',
            'RN',
            'RO',
            'RR',
            'RS',
            'SC',
            'SE',
            'SP',
            'TO',
        ];

        $total = count($ufs);
        $this->info("Iniciando sincronização de {$total} UFs...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($ufs as $uf) {
            $service->syncByUf($uf);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronização concluída.');

        return self::SUCCESS;
    }
}
