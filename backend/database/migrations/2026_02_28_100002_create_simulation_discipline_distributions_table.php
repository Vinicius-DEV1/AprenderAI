<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('simulation_discipline_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('simulation_engine_rules')->onDelete('cascade');
            $table->string('disciplina');           // e.g. "MATEMÁTICA", "PORTUGUÊS"
            $table->decimal('percentual', 5, 2);    // e.g. 50.00 (percent of total)
            $table->string('dificuldade')->nullable(); // optional: easy|medium|hard override for this subject
            $table->integer('ordem')->default(0);   // sort order for generation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_discipline_distributions');
    }
};
