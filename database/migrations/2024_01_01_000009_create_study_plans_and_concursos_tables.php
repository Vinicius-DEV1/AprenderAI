<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam_type');
            $table->string('exam_name')->nullable();
            $table->date('exam_date')->nullable();
            $table->integer('hours_per_day');
            $table->text('plan_json')->nullable();
            $table->text('stats_snapshot')->nullable();
            $table->string('status')->default('processing'); // processing, ready, failed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('next_generate_at')->nullable();
            $table->timestamp('next_update_at')->nullable();
            $table->timestamps();
        });

        Schema::create('concursos', function (Blueprint $table) {
            $table->id();
            $table->string('uf');
            $table->string('orgao');
            $table->string('cargo')->nullable();
            $table->string('situacao');
            $table->decimal('salario_maximo', 10, 2)->nullable();
            $table->integer('vagas')->nullable();
            $table->string('link_oficial')->nullable();
            $table->string('fonte');
            $table->timestamp('inscricoes_inicio')->nullable();
            $table->timestamp('inscricoes_fim')->nullable();
            $table->timestamp('ultimo_status_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concursos');
        Schema::dropIfExists('study_plans');
    }
};
