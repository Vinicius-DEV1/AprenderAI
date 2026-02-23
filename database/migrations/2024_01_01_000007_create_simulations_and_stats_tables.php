<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('simulations')) {
            Schema::create('simulations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // 'enem', 'concurso'
                $table->text('configuration');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->integer('time_elapsed')->default(0);
                $table->string('status')->default('pending'); // generating, pending, in_progress, finished, corrected, error
                $table->decimal('score', 8, 2)->nullable();
                $table->text('scores_by_subject')->nullable();
                $table->text('analysis_by_theme')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('simulation_answers')) {
            Schema::create('simulation_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('simulation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->string('user_answer')->nullable();
                $table->boolean('is_correct')->default(false);
                $table->integer('time_spent')->default(0);
                $table->boolean('marked_for_review')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_stats')) {
            Schema::create('user_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->integer('total_simulations')->default(0);
                $table->integer('total_essays')->default(0);
                $table->decimal('average_math_score', 8, 2)->default(0);
                $table->decimal('average_portuguese_score', 8, 2)->default(0);
                $table->decimal('average_overall_score', 8, 2)->default(0);
                $table->integer('total_time_studied')->default(0);
                $table->text('weak_themes')->nullable();
                $table->text('strong_themes')->nullable();
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('user_topic_stats')) {
            Schema::create('user_topic_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('subject');
                $table->string('topic');
                $table->integer('attempts')->default(0);
                $table->integer('correct')->default(0);
                $table->decimal('accuracy', 5, 2)->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_question_answers')) {
            Schema::create('user_question_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->string('selected_answer');
                $table->boolean('is_correct')->default(false);
                $table->timestamp('answered_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('question_interactions')) {
            Schema::create('question_interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('simulation_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('question_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role');
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_interactions');
        Schema::dropIfExists('user_question_answers');
        Schema::dropIfExists('user_topic_stats');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('simulation_answers');
        Schema::dropIfExists('simulations');
    }
};
