<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ServerMetricService
 *
 * Coleta métricas de uso de recursos do servidor (CPU, RAM, Disk, Rede).
 *
 * IMPORTANTE — Ambiente Docker:
 *   As leituras de /proc/meminfo e /proc/stat refletem os recursos VISÍVEIS ao container.
 *   Se a VPS tiver limites de cgroup (--memory), os valores serão relativos a esses limites.
 *   Para obter métricas do HOST real, é necessário montar /proc do host em /host_proc.
 *
 * Cálculo de Velocidade de Rede:
 *   A velocidade é calculada como DELTA: (bytes_atuais - bytes_anteriores) / tempo_decorrido.
 *   O snapshot anterior é armazenado no Cache do Laravel (driver: file/redis).
 */
class ServerMetricService
{
    /**
     * Coleta um snapshot completo das métricas para a API de tempo real.
     * Inclui velocidade de rede (delta calculado internamente).
     */
    public function collect(): array
    {
        $ram = $this->getRamStats();
        $disk = $this->getDiskStats();
        $net = $this->getNetworkDelta();

        return [
            'cpu_usage' => $this->getCpuUsage(),

            // RAM: porcentagem + valores absolutos
            'ram_usage' => $ram['percentage'],
            'ram_used_gb' => $ram['used_gb'],
            'ram_total_gb' => $ram['total_gb'],

            // Disk: porcentagem + valores absolutos
            'disk_usage' => $disk['percentage'],
            'disk_used_gb' => $disk['used_gb'],
            'disk_total_gb' => $disk['total_gb'],

            // Rede: bytes/s calculados por delta de tempo
            'net_rx_speed' => $net['rx_speed'],
            'net_tx_speed' => $net['tx_speed'],

            // Novos Módulos de Comando
            'uptime' => $this->getSystemUptimeFormatted(),
            'services' => $this->getServiceHealth(),
            'queues' => $this->getQueueStats(),
            'queue_performance' => app(\App\Services\QueueTrackerService::class)->getMetrics(),
            'top_processes' => $this->getTopProcesses(10),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CPU
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the current CPU usage percentage (non-blocking).
     *
     * Linux strategy (non-blocking):
     *   - Reads /proc/stat and stores the snapshot in the Laravel cache with a 2s TTL.
     *   - On the next call (after 2s), computes the delta between the current and
     *     cached snapshot to derive CPU %.
     *   - First call returns 0.0 (no previous baseline available yet).
     *   This avoids blocking a PHP-FPM thread with sleep(1) on every request.
     *
     * Windows strategy (dev/local only):
     *   - Uses WMIC’s LoadPercentage (blocking but only used locally).
     *
     * @return float CPU usage percentage (0–100), rounded to 1 decimal place.
     */
    public function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $check = shell_exec('wmic cpu get loadpercentage');
                return (float) preg_replace('/[^0-9.]/', '', $check);
            } catch (\Exception) {
                return 0.0;
            }
        }

        try {
            $path       = file_exists('/host_proc/stat') ? '/host_proc/stat' : '/proc/stat';
            $cacheKey   = 'server_metric_cpu_snapshot';
            $previous   = Cache::get($cacheKey);
            $statNow    = file_get_contents($path);
            $infoNow    = $this->parseProcStat($statNow);
            $capturedAt = microtime(true);

            // Always persist the latest snapshot for the next call (TTL: 10s).
            Cache::put($cacheKey, [
                'total'      => $infoNow['total'],
                'idle'       => $infoNow['idle'],
                'captured_at' => $capturedAt,
            ], now()->addSeconds(10));

            // First call: no baseline available yet.
            if (!$previous) {
                return 0.0;
            }

            $totalDelta = $infoNow['total'] - $previous['total'];
            $idleDelta  = $infoNow['idle']  - $previous['idle'];

            if ($totalDelta <= 0) {
                return 0.0;
            }

            return round((1 - ($idleDelta / $totalDelta)) * 100, 1);

        } catch (\Exception) {
            // Emergency fallback: load average is not a strict percentage,
            // but gives a rough indication of system load.
            return round(sys_getloadavg()[0] * 10, 1);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RAM
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lê o uso de RAM a partir de /proc/meminfo (Linux).
     *
     * Retorna:
     *   - percentage : float  (ex: 51.5)
     *   - used_gb    : float  (ex: 4.12) — memória em uso (Total - Disponível)
     *   - total_gb   : float  (ex: 8.00) — memória total
     *
     * Nota: usa MemAvailable (não MemFree) para excluir buffers/cache que o kernel
     * readmite automaticamente — resulta em uma estimativa mais precisa do "real uso".
     */
    public function getRamStats(): array
    {
        $default = ['percentage' => 0.0, 'used_gb' => 0.0, 'total_gb' => 0.0];

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $wmic = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
                preg_match('/FreePhysicalMemory=(\d+)/', $wmic, $free);
                preg_match('/TotalVisibleMemorySize=(\d+)/', $wmic, $total);

                if (isset($free[1], $total[1])) {
                    $totalKb = (int) $total[1];
                    $usedKb = $totalKb - (int) $free[1];
                    return [
                        'percentage' => round(($usedKb / $totalKb) * 100, 1),
                        'used_gb' => round($usedKb / 1024 / 1024, 2),
                        'total_gb' => round($totalKb / 1024 / 1024, 2),
                    ];
                }
            } catch (\Exception $e) {
            }
            return $default;
        }

        try {
            $path = file_exists('/host_proc/meminfo') ? '/host_proc/meminfo' : '/proc/meminfo';
            $memInfo = file_get_contents($path);
            // Valores em kB
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);

            if (isset($total[1], $available[1])) {
                $totalKb = (int) $total[1];
                $availableKb = (int) $available[1];
                $usedKb = $totalKb - $availableKb;

                return [
                    'percentage' => round(($usedKb / $totalKb) * 100, 1),
                    'used_gb' => round($usedKb / 1024 / 1024, 2),
                    'total_gb' => round($totalKb / 1024 / 1024, 2),
                ];
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    /**
     * Compatibilidade com código legado que chama getRamUsage() (float).
     */
    public function getRamUsage(): float
    {
        return $this->getRamStats()['percentage'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISCO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Lê o uso do disco (partição raiz '/') usando disk_free_space() e disk_total_space().
     *
     * Estas funções do PHP são portáveis (Linux e Windows) e não precisam de shell_exec.
     * Retorna o uso da partição onde o PHP está sendo executado.
     */
    public function getDiskStats(string $mountPoint = '/'): array
    {
        $default = ['percentage' => 0.0, 'used_gb' => 0.0, 'total_gb' => 0.0];

        try {
            $path = PHP_OS_FAMILY === 'Windows' ? 'C:' : $mountPoint;

            $totalBytes = disk_total_space($path);
            $freeBytes = disk_free_space($path);

            if ($totalBytes === false || $totalBytes === 0) {
                return $default;
            }

            $usedBytes = $totalBytes - $freeBytes;

            return [
                'percentage' => round(($usedBytes / $totalBytes) * 100, 1),
                'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
                'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            Log::warning('ServerMetricService: falha ao ler disco — ' . $e->getMessage());
            return $default;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REDE (Delta)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula a velocidade de rede atual em bytes/s usando delta entre dois snapshots.
     *
     * Estratégia:
     *   1. Lê os bytes acumulados de TODAS as interfaces em /proc/net/dev.
     *   2. Exclui 'lo' (loopback) e 'docker*' / 'veth*' (redes internas do Docker).
     *   3. Compara com o snapshot anterior armazenado no Cache.
     *   4. Calcula: velocidade = (bytes_agora - bytes_antes) / tempo_decorrido_em_segundos.
     *
     * Na primeira chamada, retorna 0 (não há baseline anterior).
     */
    public function getNetworkDelta(): array
    {
        $default = ['rx_speed' => 0, 'tx_speed' => 0];

        if (PHP_OS_FAMILY === 'Windows') {
            return $default;
        }

        try {
            $current = $this->readProcNetDev();
            $now = microtime(true);

            $cacheKey = 'server_metric_net_snapshot';
            $previous = Cache::get($cacheKey);

            // Salva o snapshot atual para a próxima chamada
            Cache::put($cacheKey, ['rx' => $current['rx'], 'tx' => $current['tx'], 'ts' => $now], 60);

            if (!$previous) {
                // Primeira chamada — sem baseline
                return $default;
            }

            $elapsed = $now - ($previous['ts'] ?? $now);

            if ($elapsed <= 0) {
                return $default;
            }

            // bytes/s = delta_bytes / delta_segundos
            $rxSpeed = max(0, ($current['rx'] - $previous['rx']) / $elapsed);
            $txSpeed = max(0, ($current['tx'] - $previous['tx']) / $elapsed);

            return [
                'rx_speed' => (int) round($rxSpeed),
                'tx_speed' => (int) round($txSpeed),
            ];
        } catch (\Exception $e) {
            Log::warning('ServerMetricService: falha ao calcular delta de rede — ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Lê os bytes acumulados de RX e TX de /proc/net/dev.
     * Exclui interfaces que não representam tráfego real do servidor:
     *   - lo   : loopback
     *   - docker*, veth*, br-* : redes internas do Docker
     */
    protected function readProcNetDev(): array
    {
        $path = file_exists('/host_proc/net/dev') ? '/host_proc/net/dev' : '/proc/net/dev';
        $stats = file_get_contents($path);
        $lines = explode("\n", $stats);
        $rxTotal = 0;
        $txTotal = 0;

        // Interfaces a ignorar (prefixos)
        $ignorePrefixes = ['lo', 'docker', 'veth', 'br-', 'virbr'];

        foreach ($lines as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            // Extrai nome da interface (antes dos ':')
            [$ifacePart, $dataPart] = explode(':', $line, 2);
            $iface = trim($ifacePart);

            // Verifica se deve ignorar
            $skip = false;
            foreach ($ignorePrefixes as $prefix) {
                if (str_starts_with($iface, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip)
                continue;

            // Divide os valores numéricos
            $values = preg_split('/\s+/', trim($dataPart));

            // Layout /proc/net/dev (colunas):
            // [0]=rx_bytes [1]=rx_packets ... [8]=tx_bytes [9]=tx_packets ...
            $rxTotal += (int) ($values[0] ?? 0);
            $txTotal += (int) ($values[8] ?? 0);
        }

        return ['rx' => $rxTotal, 'tx' => $txTotal];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NOVOS COMPONENTES (UPTIME, SAÚDE, PROCESSOS, FILAS)
    // ─────────────────────────────────────────────────────────────────────────

    public function getSystemUptimeSeconds(): float
    {
        if (PHP_OS_FAMILY === 'Windows')
            return 0;
        try {
            $path = file_exists('/host_proc/uptime') ? '/host_proc/uptime' : '/proc/uptime';
            if (is_readable($path)) {
                $uptime = explode(' ', file_get_contents($path));
                return (float) $uptime[0];
            }
        } catch (\Exception $e) {
        }
        return 0;
    }

    public function getSystemUptimeFormatted(): string
    {
        $seconds = (int) $this->getSystemUptimeSeconds();
        if ($seconds === 0)
            return '0s';

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0)
            $parts[] = $days . 'd';
        if ($hours > 0)
            $parts[] = $hours . 'h';
        $parts[] = $minutes . 'm';

        return implode(' ', $parts);
    }

    public function getServiceHealth(): array
    {
        $mysql = false;
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $mysql = true;
        } catch (\Exception $e) {
        }

        $redis = false;
        try {
            $redisConnection = \Illuminate\Support\Facades\Redis::connection();
            if ($redisConnection) {
                $redisConnection->ping();
                $redis = true;
            }
        } catch (\Exception $e) {
        }

        return [
            'database' => $mysql,
            'redis' => $redis,
            'app' => true, // Se respondeu, está vivo
            'webserver' => true, // Presumido vivo se a request passou
        ];
    }

    public function getQueueStats(): array
    {
        try {
            $pending = \Illuminate\Support\Facades\Queue::size('default') + \Illuminate\Support\Facades\Queue::size('import');
            $failed = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();
            return [
                'pending' => $pending,
                'failed' => $failed,
                'workers' => 'Ativo',
            ];
        } catch (\Exception $e) {
            return ['pending' => 0, 'failed' => 0, 'workers' => 'Offline'];
        }
    }

    public function getTopProcesses(int $limit = 10): array
    {
        if (PHP_OS_FAMILY === 'Windows')
            return [];

        $procPath = file_exists('/host_proc') ? '/host_proc' : '/proc';
        $processes = [];
        $uptime = $this->getSystemUptimeSeconds();
        $pageSize = 4096;
        $hertz = 100; // USER_HZ padrão

        $dirs = @glob($procPath . '/[0-9]*', GLOB_ONLYDIR);
        if (!$dirs || $uptime <= 0)
            return [];

        // Para evitar gargalo de I/O, lemos apenas o necessário
        foreach ($dirs as $dir) {
            $pid = basename($dir);
            $statFile = $dir . '/stat';
            $statmFile = $dir . '/statm';

            if (!is_readable($statFile))
                continue;

            $stat = @file_get_contents($statFile);
            if (!$stat)
                continue;

            // Formato stat Linux: o comm está entre parênteses
            if (!preg_match('/^(\d+) \((.*)\) [A-Z] (.*)$/', $stat, $matches))
                continue;

            $comm = $matches[2];
            $fields = explode(' ', $matches[3]);

            $utime = (int) ($fields[11] ?? 0);
            $stime = (int) ($fields[12] ?? 0);
            $starttime = (int) ($fields[19] ?? 0);

            $totalTimeSeconds = ($utime + $stime) / $hertz;
            $secondsSinceStart = $uptime - ($starttime / $hertz);
            $cpuPercent = 0;
            if ($secondsSinceStart > 0) {
                $cpuPercent = ($totalTimeSeconds / $secondsSinceStart) * 100;
            }

            $memBytes = 0;
            if (is_readable($statmFile)) {
                $statm = @file_get_contents($statmFile);
                if ($statm) {
                    $statmFields = explode(' ', trim($statm));
                    $rssPages = (int) ($statmFields[1] ?? 0);
                    $memBytes = $rssPages * $pageSize;
                }
            }

            $processes[] = [
                'pid' => $pid,
                'name' => $comm,
                'cpu' => round($cpuPercent, 1),
                'mem_bytes' => $memBytes,
            ];
        }

        // Ordena por CPU descendente usando array_multisort para máxima performance
        $cpuCol = array_column($processes, 'cpu');
        array_multisort($cpuCol, SORT_DESC, $processes);

        return array_slice($processes, 0, $limit);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITÁRIOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Analisa a linha 'cpu' de /proc/stat e retorna total e idle.
     * Formato: cpu user nice system idle iowait irq softirq steal guest ...
     */
    private function parseProcStat(string $content): array
    {
        $lines = explode("\n", $content);
        $parts = preg_split('/\s+/', trim($lines[0]));
        // $parts[0] = 'cpu', $parts[1..] = valores numéricos

        $total = 0;
        foreach (array_slice($parts, 1) as $val) {
            $total += (int) $val;
        }

        return [
            'total' => $total,
            'idle' => (int) ($parts[4] ?? 0), // coluna 4 = idle
        ];
    }
}
