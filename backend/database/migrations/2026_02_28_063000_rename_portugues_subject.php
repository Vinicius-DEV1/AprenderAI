<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function run(): void
    {
        // 1. Update subjects table
        // Normalize names to handle variations
        DB::table('subjects')
            ->whereIn(DB::raw('UPPER(name)'), ['PORTUGUÊS', 'PORTUGUES'])
            ->update(['name' => 'LÍNGUA PORTUGUESA']);

        // 2. Update simulation_discipline_distributions table
        DB::table('simulation_discipline_distributions')
            ->whereIn(DB::raw('UPPER(disciplina)'), ['PORTUGUÊS', 'PORTUGUES'])
            ->update(['disciplina' => 'LÍNGUA PORTUGUESA']);

        // 3. Update UserTopicStat if exists (historical data)
        if (Schema::hasTable('user_topic_stats')) {
            DB::table('user_topic_stats')
                ->whereIn(DB::raw('UPPER(subject)'), ['PORTUGUÊS', 'PORTUGUES'])
                ->update(['subject' => 'LÍNGUA PORTUGUESA']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subjects')
            ->where('name', 'LÍNGUA PORTUGUESA')
            ->update(['name' => 'PORTUGUÊS']);

        DB::table('simulation_discipline_distributions')
            ->where('disciplina', 'LÍNGUA PORTUGUESA')
            ->update(['disciplina' => 'PORTUGUÊS']);

        if (Schema::hasTable('user_topic_stats')) {
            DB::table('user_topic_stats')
                ->where('subject', 'LÍNGUA PORTUGUESA')
                ->update(['subject' => 'PORTUGUÊS']);
        }
    }
};
