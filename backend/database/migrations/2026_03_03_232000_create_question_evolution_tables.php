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
        // 1. Notebooks Table
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Notebook Questions Table (Pivot)
        Schema::create('notebook_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Evita duplicidade de questão no mesmo caderno
            $table->unique(['notebook_id', 'question_id']);
        });

        // 3. Favorites Table
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
        });

        // 4. Question Reports Table
        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason'); // Motivo do report
            $table->string('status')->default('pending'); // pending, resolved
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Question Notes Table
        Schema::create('question_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('content'); // Conteúdo Markdown
            $table->timestamps();
        });

        // 6. Add is_active to Questions
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('question_notes');
        Schema::dropIfExists('question_reports');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('notebook_questions');
        Schema::dropIfExists('notebooks');
    }
};
