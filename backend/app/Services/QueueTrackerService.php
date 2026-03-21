<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * QueueTrackerService
 *
 * Records per-queue job completion events and exposes rolling performance metrics.
 *
 * Metrics are stored in Redis sorted sets and integer counters with short TTLs
 * to provide a live "last 60 seconds" view without expensive database queries.
 *
 * Usage:
 *   - Call recordJob() from any job's handle() method after processing completes.
 *   - Call getMetrics() from the admin monitor endpoint for the dashboard.
 */
class QueueTrackerService
{
    /** Redis key prefix for all tracker keys. */
    private const PREFIX = 'monitor:queue:';

    /** Rolling window in seconds for throughput calculation. */
    private const WINDOW_SECONDS = 60;

    /**
     * Records a job completion event and its processing duration.
     *
     * Stores two Redis structures per queue:
     *   - A sorted set for throughput (timestamps as score + member)
     *   - INCRBYFLOAT/INCR counters for average duration per 1-minute bucket
     *
     * @param string $queue           Queue name (e.g. 'high', 'embeddings')
     * @param float  $durationSeconds Wall-clock seconds the job took to process
     */
    public function recordJob(string $queue, float $durationSeconds): void
    {
        $now   = microtime(true);
        $redis = Redis::connection();

        // ── Throughput tracking (rolling sorted set) ───────────────────────────
        // Each entry: score = timestamp (float), member = timestamp string.
        // Entries older than WINDOW_SECONDS are pruned on every write.
        $redis->zadd(self::PREFIX . "throughput:{$queue}", $now, $now);
        $redis->zremrangebyscore(self::PREFIX . "throughput:{$queue}", 0, $now - self::WINDOW_SECONDS);

        // ── Average duration tracking (fixed 1-minute bucket) ─────────────────
        // We keep a sum + count per integer minute bucket (TTL: 5 min).
        // getMetrics() falls back to the previous minute if the current is empty.
        $minute   = (int) floor($now / 60);
        $keySum   = self::PREFIX . "duration_sum:{$queue}:{$minute}";
        $keyCount = self::PREFIX . "duration_count:{$queue}:{$minute}";

        $redis->incrbyfloat($keySum, $durationSeconds);
        $redis->incr($keyCount);
        $redis->expire($keySum, 300);
        $redis->expire($keyCount, 300);
    }

    /**
     * Returns performance metrics for all active production queues.
     *
     * Metrics per queue:
     *   - jobs_per_minute  : count of jobs completed in the last 60 seconds
     *   - avg_duration_seconds : rolling average processing time (1-min bucket)
     *
     * @return array<string, array{jobs_per_minute: int, avg_duration_seconds: float}>
     */
    public function getMetrics(): array
    {
        // All queues handled by worker-default in priority order.
        $queues = ['high', 'search_embeddings', 'ai_triage', 'default', 'embeddings', 'low'];

        $now           = microtime(true);
        $currentMinute = (int) floor($now / 60);
        $redis         = Redis::connection();

        $metrics = [];

        foreach ($queues as $queue) {
            // Count jobs completed in the last 60 seconds via the sorted set.
            $throughput = $redis->zcount(
                self::PREFIX . "throughput:{$queue}",
                $now - self::WINDOW_SECONDS,
                $now
            );

            // Try the current minute bucket first; fall back to the previous if empty.
            $sum   = (float) ($redis->get(self::PREFIX . "duration_sum:{$queue}:{$currentMinute}") ?: 0);
            $count = (int)   ($redis->get(self::PREFIX . "duration_count:{$queue}:{$currentMinute}") ?: 0);

            if ($count === 0) {
                $prev  = $currentMinute - 1;
                $sum   = (float) ($redis->get(self::PREFIX . "duration_sum:{$queue}:{$prev}") ?: 0);
                $count = (int)   ($redis->get(self::PREFIX . "duration_count:{$queue}:{$prev}") ?: 0);
            }

            $metrics[$queue] = [
                'jobs_per_minute'      => $throughput,
                'avg_duration_seconds' => $count > 0 ? round($sum / $count, 3) : 0,
            ];
        }

        return $metrics;
    }
}
