<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // AVISO CRÍTICO DE ARQUITETURA
        // Estas tabelas formam o modelo N:N da aplicação. Nenhuma chave estrangeira 
        // de relacionamentos lógicos (ex: subject_id) deve ser registrada.

        if (!Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('type')->nullable(); // Ex: 'enem', 'concurso', 'shared'
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('topics')) {
            Schema::create('topics', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subjects');
    }
};
