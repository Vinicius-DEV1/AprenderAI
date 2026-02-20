<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('question_alternatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('label', 1); // A, B, C, D, E...
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index(['question_id', 'is_correct']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('format', ['multiple_choice', 'true_false'])->default('multiple_choice')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('format');
        });

        Schema::dropIfExists('question_alternatives');
    }
};
