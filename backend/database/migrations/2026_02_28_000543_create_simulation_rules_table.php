<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simulation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('simulation_presets')->onDelete('cascade');
            $table->string('category'); // subject_distribution, general_config
            $table->json('configuration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_rules');
    }
};
