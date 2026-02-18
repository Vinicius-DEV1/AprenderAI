<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    public function up(): void
    {
        Schema::table('question_interactions', function (Blueprint $table) {
            // Tornar simulation_id nullable para suportar chat avulso (fora de simulados)
            $table->unsignedBigInteger('simulation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_interactions', function (Blueprint $table) {
            $table->unsignedBigInteger('simulation_id')->nullable(false)->change();
        });
    }
};
