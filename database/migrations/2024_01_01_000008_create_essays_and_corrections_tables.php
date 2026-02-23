<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('essays')) {
            Schema::create('essays', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('simulation_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type')->default('enem'); // enem, concurso
                $table->string('title');
                $table->longText('content');
                $table->string('status')->default('pending'); // pending, in_progress, evaluating, correcting, corrected, completed, error
                $table->integer('score')->nullable();
                $table->text('competencies')->nullable();
                $table->text('feedback')->nullable();
                $table->text('ai_suggestions')->nullable();
                $table->text('example_essay')->nullable();
                $table->integer('time_limit')->nullable();
                $table->text('topic_description')->nullable();
                $table->integer('topic_regen_count')->default(0);
                $table->string('topic_hash')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('evaluated_at')->nullable();
                $table->text('feedback_json')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('corrections')) {
            Schema::create('corrections', function (Blueprint $table) {
                $table->id();
                $table->morphs('correctable'); // creates correctable_id and correctable_type
                $table->string('ai_provider')->nullable();
                $table->string('ai_model')->nullable();
                $table->integer('input_tokens')->nullable();
                $table->integer('output_tokens')->nullable();
                $table->integer('total_tokens')->nullable();
                $table->integer('tokens_used')->default(0);
                $table->longText('correction_data')->nullable();
                $table->timestamp('corrected_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
        Schema::dropIfExists('essays');
    }
};
