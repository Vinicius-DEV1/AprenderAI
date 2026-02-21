<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'enem', 'concurso'
            $table->string('format')->default('multiple_choice');
            $table->string('difficulty')->default('medium');
            $table->text('difficulty_reasoning')->nullable();
            $table->integer('year')->nullable();
            $table->text('statement');
            $table->text('explanation')->nullable();
            $table->string('source')->default('manual');
            $table->string('organization')->nullable();
            $table->string('institution')->nullable();
            $table->string('role')->nullable();
            $table->string('theme')->nullable(); // Guardado de Forma Original (Eixos ENEM)
            $table->string('topic')->nullable(); // Guardado de Forma Original (Assuntos Concurso)
            $table->string('external_id');
            $table->string('review_status')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('question_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // 'A', 'B', 'C', 'D', 'E'
            $table->text('content')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('question_imports', function (Blueprint $table) {
            $table->id();
            $table->string('batch_name');
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->integer('total_questions')->default(0);
            $table->integer('pending_count')->default(0);
            $table->integer('approved_count')->default(0);
            $table->string('status')->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('question_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('question_imports')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_items');
        Schema::dropIfExists('question_imports');
        Schema::dropIfExists('question_alternatives');
        Schema::dropIfExists('questions');
    }
};
