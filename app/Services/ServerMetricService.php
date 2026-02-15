<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ServerMetricService
{
    public function collect(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'ram_usage' => $this->getRamUsage(),
            'net_rx_speed' => 0, // Calculated by Command via delta
            'net_tx_speed' => 0,
        ];
    }

    public function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Use wmic
            try {
                $check = shell_exec('wmic cpu get loadpercentage');
                return (float) preg_replace('/[^0-9.]/', '', $check);
            } catch (\Exception $e) {
                return 0.0;
            }
        } 
        
        // Linux: Read /proc/stat or sys_getloadavg
        // Using sys_getloadavg is easier but it's load not %.
        // Let's try to estimate % from simple calculation or top/mpstat if available.
        // For simplicity and standard VPS, reading /proc/stat is reliable.
        try {
            $stat1 = file_get_contents('/proc/stat');
            sleep(1);
            $stat2 = file_get_contents('/proc/stat');
            
            $info1 = $this->parseProcStat($stat1);
            $info2 = $this->parseProcStat($stat2);
            
            $total = $info2['total'] - $info1['total'];
            $idle = $info2['idle'] - $info1['idle'];
            
            return $total > 0 ? (1 - ($idle / $total)) * 100 : 0;
        } catch (\Exception $e) {
            return sys_getloadavg()[0] * 10; // Fallback estimate
        }
    }

    public function getRamUsage(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {
            try {
                $wmic = shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value');
                preg_match('/FreePhysicalMemory=(\d+)/', $wmic, $free);
                preg_match('/TotalVisibleMemorySize=(\d+)/', $wmic, $total);
                
                if (isset($free[1]) && isset($total[1])) {
                    $total = $total[1];
                    $free = $free[1];
                    return round((($total - $free) / $total) * 100, 2);
                }
            } catch (\Exception $e) {}
            return 0.0;
        }

        // Linux
        try {
            $memInfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $available);
            
            if (isset($total[1]) && isset($available[1])) {
                return round((($total[1] - $available[1]) / $total[1]) * 100, 2);
            }
        } catch (\Exception $e) {}
        
        return 0.0;
    }

    public function getNetworkStats(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows is hard to get bytes without perform counters which are localized.
            // Using a simpler approach or returning 0 for Dev.
            return ['rx' => 0, 'tx' => 0];
        }

        // Linux: /proc/net/dev
        try {
            $stats = file_get_contents('/proc/net/dev');
            $lines = explode("\n", $stats);
            $rx = 0;
            $tx = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    // eth0: 123 456 ...
                    $parts = preg_split('/\s+/', trim($line));
                    // parts[0] is interface name with colon? or just name? 
                    // Usually: "eth0: 1 2 3 4 ..." -> split -> ["eth0:", "1", "2"]
                    // If preg_split removes empty, index 1 is rx_bytes, index 9 is tx_bytes
                    if (count($parts) >= 10) {
                         // Removing interface name part to get numbers
                        $values = preg_split('/\s+/', trim(substr($line, strpos($line, ':') + 1)));
                        $rx += $values[0] ?? 0;
                        $tx += $values[8] ?? 0;
                    }
                }
            }
            
            return ['rx' => $rx, 'tx' => $tx]; // Total bytes until now
        } catch (\Exception $e) {
            return ['rx' => 0, 'tx' => 0];
        }
    }

    private function parseProcStat($content)
    {
        // cpu  2255 34 2290 22625563 ...
        $lines = explode("\n", $content);
        $parts = preg_split('/\s+/', trim($lines[0]));
        // $parts[0] = 'cpu'
        // $parts[1] = user, [2]=nice, [3]=system, [4]=idle
        $total = 0;
        foreach (array_slice($parts, 1) as $val) {
            $total += $val;
        }
        
        return [
            'total' => $total,
            'idle' => $parts[4] ?? 0
        ];
    }
}
