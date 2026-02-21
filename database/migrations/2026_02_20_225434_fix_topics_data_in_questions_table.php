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
        // Itera sobre as questões que possuem mais de uma matéria linkada
        // e move a segunda matéria para a coluna 'topic' (Assunto).
        \App\Models\Question::with('subjects')->chunkById(100, function ($questions) {
            foreach ($questions as $question) {
                if ($question->subjects->count() > 1) {
                    // O primeiro subject costuma ser a Matéria (ex: Matemática)
                    // O segundo costuma ser o Assunto (ex: Logaritmo)
                    $topicName = $question->subjects->get(1)->name;
                    $question->update(['topic' => $topicName]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\Question::whereNotNull('topic')->update(['topic' => null]);
    }
};
