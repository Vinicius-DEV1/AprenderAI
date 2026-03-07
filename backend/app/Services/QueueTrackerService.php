<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class QueueTrackerService
{
    private const PREFIX = 'monitor:queue:';
    private const WINDOW_SECONDS = 60;

    /**
     * Records a job's completion and its duration.
     */
    public function recordJob(string $queue, float $durationSeconds): void
    {
        $now = microtime(true);
        $redis = Redis::connection();

        // 1. Throughput: Store timestamp in a sorted set (Rolling Window)
        $redis->zadd(self::PREFIX . "throughput:{$queue}", $now, $now);

        // Cleanup old entries (> 60s) occasionally or every time
        $redis->zremrangebyscore(self::PREFIX . "throughput:{$queue}", 0, $now - self::WINDOW_SECONDS);

        // 2. Average Duration: Store sum and count for the last 5 minutes (approx)
        // We use a simple INCR/INCRBYFLOAT and reset it every 5 minutes OR use a rolling avg
        // To keep it simple and accurate for "current" state, we'll use a fixed-window of 1 minute.
        $minute = floor($now / 60);
        $keySum = self::PREFIX . "duration_sum:{$queue}:{$minute}";
        $keyCount = self::PREFIX . "duration_count:{$queue}:{$minute}";

        $redis->incrbyfloat($keySum, $durationSeconds);
        $redis->incr($keyCount);

        // TTL for cleanup
        $redis->expire($keySum, 300);
        $redis->expire($keyCount, 300);
    }

    /**
     * Gets performance metrics for all relevant queues.
     */
    public function getMetrics(): array
    {
        $queues = ['default', 'essays', 'ai-batches', 'import'];
        $now = microtime(true);
        $currentMinute = floor($now / 60);
        $redis = Redis::connection();

        $metrics = [];

        foreach ($queues as $queue) {
            // Count jobs in the last 60 seconds
            $throughput = $redis->zcount(self::PREFIX . "throughput:{$queue}", $now - 60, $now);

            // Get duration from current or previous minute if current is empty
            $sum = (float) $redis->get(self::PREFIX . "duration_sum:{$queue}:{$currentMinute}") ?: 0;
            $count = (int) $redis->get(self::PREFIX . "duration_count:{$queue}:{$currentMinute}") ?: 0;

            if ($count === 0) {
                // Try previous minute
                $prevMinute = $currentMinute - 1;
                $sum = (float) $redis->get(self::PREFIX . "duration_sum:{$queue}:{$prevMinute}") ?: 0;
                $count = (int) $redis->get(self::PREFIX . "duration_count:{$queue}:{$prevMinute}") ?: 0;
            }

            $avgDuration = $count > 0 ? ($sum / $count) : 0;

            $metrics[$queue] = [
                'jobs_per_minute' => $throughput,
                'avg_duration_seconds' => round($avgDuration, 3),
            ];
        }

        return $metrics;
    }
}
