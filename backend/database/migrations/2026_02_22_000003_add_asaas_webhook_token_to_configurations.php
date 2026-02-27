<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the 'configurations' table with the asaas_webhook_token key so it
 * shows up in the admin panel even before the admin explicitly sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only insert if the key doesn't exist yet
        $exists = DB::table('configurations')->where('key', 'asaas_webhook_token')->exists();
        if (!$exists) {
            DB::table('configurations')->insert([
                'key'        => 'asaas_webhook_token',
                'value'      => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('configurations')->where('key', 'asaas_webhook_token')->delete();
    }
};
