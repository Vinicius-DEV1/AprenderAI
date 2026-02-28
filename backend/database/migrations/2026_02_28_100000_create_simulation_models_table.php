<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The new structured simulation_models table replaces the old simulation_presets generic approach
        Schema::create('simulation_models', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // enem_prova_completa, enem_matematica, concurso_flexivel
            $table->string('nome');           // Human readable label shown in admin
            $table->string('tipo')->default('enem'); // enem | concurso
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_models');
    }
};
