<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Cria a tabela 'topics'
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Cria a tabela pivô 'question_topic'
        Schema::create('question_topic', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            
            // Define chave primária composta
            $table->primary(['question_id', 'topic_id']);
            
            // Índice adicional para melhorar performance de consultas inversas
            $table->index('topic_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('question_topic');
        Schema::dropIfExists('topics');
    }
};
