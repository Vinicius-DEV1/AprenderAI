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
        Schema::create('user_topic_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject')->index();
            $table->string('topic')->index();
            $table->integer('attempts')->default(0);
            $table->integer('correct')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject', 'topic']);
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam_type'); // enem, concurso
            $table->string('exam_name')->nullable();
            $table->date('exam_date')->nullable();
            $table->integer('hours_per_day');
            $table->json('plan_json');
            $table->json('stats_snapshot')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamp('next_generate_at')->nullable();
            $table->timestamp('next_update_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('user_topic_stats');
    }
};
