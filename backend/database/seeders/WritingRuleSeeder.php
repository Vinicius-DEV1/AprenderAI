<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WritingRuleSeeder extends Seeder
{
    /**
     * Idempotent seeder — uses upsert so it's safe to run multiple times.
     * Inserts/updates exactly 2 rows: enem and concurso.
     */
    public function run(): void
    {
        DB::table('writing_rules')->upsert(
            [
                [
                    'type' => 'enem',
                    'min_chars' => 1500,
                    'max_chars' => 3000,
                    'max_lines' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'type' => 'concurso',
                    'min_chars' => 1500,
                    'max_chars' => 4000,
                    'max_lines' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['type'],                                // unique key to match on
            ['min_chars', 'max_chars', 'max_lines', 'updated_at'] // columns to update
        );

        $this->command->info('WritingRuleSeeder: 2 regras inseridas/atualizadas (enem, concurso).');
    }
}
