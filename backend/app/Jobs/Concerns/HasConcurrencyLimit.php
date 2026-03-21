<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * HasConcurrencyLimit
 *
 * Redis-based distributed semaphore for Laravel Jobs.
 *
 * Prevents more than N concurrent executions of the same job type
 * across the entire worker cluster, regardless of how many workers exist.
 *
 * Usage:
 *   1. Add `use HasConcurrencyLimit;` to your Job class.
 *   2. Call `$this->acquireSlot('my_key', $maxConcurrent)` at the start of handle().
 *   3. Call `$this->releaseSlot('my_key')` in a finally{} block.
 *
 * If no slot is available, the job is released back to the queue automatically.
 */
trait HasConcurrencyLimit
{
    /**
     * Tries to acquire a concurrency slot from the distributed semaphore.
     *
     * @param  string  $key        Semaphore key (e.g. 'embeddings', 'ai_triage', 'import')
     * @param  int     $max        Maximum number of concurrent executions allowed
     * @param  int     $retryIn    Seconds to wait before requeuing (default: 20s)
     * @return bool    True if slot acquired and job should proceed; False if re-queued.
     */
    protected function acquireSlot(string $key, int $max, int $retryIn = 20): bool
    {
        $slotKey = "concurrency_slot:{$key}";
        $lockKey = "concurrency_lock:{$key}";

        $acquired = false;

        // Use a short-lived atomic lock to safely read-increment the counter
        Cache::lock($lockKey, 5)->block(3, function () use ($slotKey, $max, &$acquired) {
            $current = (int) Cache::get($slotKey, 0);
            if ($current < $max) {
                // Store with a safety TTL so crashed workers don't permanently block slots
                Cache::put($slotKey, $current + 1, now()->addMinutes(10));
                $acquired = true;
            }
        });

        if (!$acquired) {
            Log::debug("[Concurrency] No slot available for '{$key}' (max={$max}). Re-releasing job in {$retryIn}s.");
            $this->release($retryIn);
            return false;
        }

        return true;
    }

    /**
     * Releases a previously acquired concurrency slot.
     * MUST be called in a finally{} block to guarantee slot is returned even on failure.
     *
     * @param  string  $key  Same key used in acquireSlot()
     */
    protected function releaseSlot(string $key): void
    {
        $slotKey = "concurrency_slot:{$key}";
        $lockKey = "concurrency_lock:{$key}";

        Cache::lock($lockKey, 5)->block(3, function () use ($slotKey) {
            $current = (int) Cache::get($slotKey, 0);
            // Decrement but never go below 0
            Cache::put($slotKey, max(0, $current - 1), now()->addMinutes(10));
        });
    }
}
