<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;
use App\Services\AI\AIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckFailedApiKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai_keys:recover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks offline or quota_exceeded API keys and attempts to recover them.';

    /**
     * Execute the console command.
     */
    public function handle(AIService $aiService)
    {
        $this->info('Starting AI Api Keys health check...');

        $failedKeys = ApiKey::whereIn('status', ['offline', 'quota_exceeded'])->get();

        if ($failedKeys->isEmpty()) {
            $this->info('No failed API keys found.');
            return;
        }

        $recoveredCount = 0;

        foreach ($failedKeys as $key) {
            $this->line("Checking key ID: {$key->id} ({$key->provider}) - Current Status: {$key->status}");

            // Uses the lightweight model-listing approach to just "ping" the APIs and confirm identity/quota.
            $result = $aiService->validateKey($key->provider, $key->decrypted_key);

            if ($result['is_valid']) {
                $key->update([
                    'status' => 'online',
                    'last_error_message' => null,
                ]);
                
                // Remove out of the short-term failover blacklist if they were in it
                $bannedIds = Cache::get('api_key_blacklist', []);
                if (($index = array_search($key->id, $bannedIds)) !== false) {
                    unset($bannedIds[$index]);
                    Cache::put('api_key_blacklist', array_values($bannedIds), now()->addMinutes(60));
                }

                $this->info("✓ Key ID {$key->id} recovered successfully.");
                Log::info("API Key Recovered via Health Check", ['id' => $key->id, 'provider' => $key->provider]);
                
                $recoveredCount++;
            } else {
                $statusError = $result['error'] ?? 'Unknown error';
                $this->error("x Key ID {$key->id} still failed: {$statusError}");
                
                // Optionally update the last error timestamp here to reflect the recent check
                $key->update([
                    'last_error_message' => "[HealthCheck] {$statusError}",
                    'last_error_at' => now(),
                ]);
            }
        }

        $this->info("Health check completed. Recovered: {$recoveredCount} keys.");
    }
}
