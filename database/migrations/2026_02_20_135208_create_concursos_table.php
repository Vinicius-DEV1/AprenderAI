<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('concursos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uf', 2);
            $table->string('orgao');
            $table->string('cargo')->nullable();
            $table->string('situacao');
            $table->decimal('salario_maximo', 12, 2)->nullable();
            $table->integer('vagas')->nullable();
            $table->string('link_oficial')->nullable();
            $table->string('fonte');
            $table->dateTime('inscricoes_inicio')->nullable();
            $table->dateTime('inscricoes_fim')->nullable();
            $table->dateTime('ultimo_status_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concursos');
    }
};
