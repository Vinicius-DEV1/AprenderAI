<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiKey;
use App\Models\ApiKeyCapability;
use Illuminate\Support\Facades\DB;

class MigrateApiKeyCapabilities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai_keys:migrate-capabilities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates legacy JSON capabilities to the new api_key_capabilities M:N pivot table and establishes priority order based on old legacy rules.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Migration of API Key Capabilities...');

        $keys = ApiKey::all();
        $migratedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($keys as $key) {
                $capabilities = $key->capabilities;

                if (!empty($capabilities) && is_array($capabilities)) {
                    foreach ($capabilities as $cap) {
                        // Check if it already exists to prevent duplicates if ran multiple times
                        $exists = ApiKeyCapability::where('api_key_id', $key->id)
                            ->where('capability', $cap)
                            ->exists();

                        if (!$exists) {
                            // Assign priority. If it was primary, it gets priority 1. 
                            // Otherwise, it gets the next available number.
                            $priority = $key->is_primary ? 1 : (ApiKeyCapability::where('capability', $cap)->max('priority') ?? 0) + 1;

                            ApiKeyCapability::create([
                                'api_key_id' => $key->id,
                                'capability' => $cap,
                                'priority' => $priority
                            ]);
                            $migratedCount++;
                        }
                    }
                }
            }

            DB::commit();
            $this->info("Migration completed successfully! Migrated {$migratedCount} capability links.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Migration failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
