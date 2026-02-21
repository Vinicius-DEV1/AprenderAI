<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Preenche registros existentes com um external_id genérico antes de aplicar NOT NULL
        \Illuminate\Support\Facades\DB::table('questions')->whereNull('external_id')->chunkById(100, function ($questions) {
            foreach ($questions as $question) {
                \Illuminate\Support\Facades\DB::table('questions')
                    ->where('id', $question->id)
                    ->update(['external_id' => 'legacy_' . $question->id]);
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            // $table->dropIndex(['external_id']);
            $table->string('external_id')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->string('external_id')->nullable()->index()->change();
        });
    }
};
