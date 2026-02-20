<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $questions = DB::table('questions')->get();

        foreach ($questions as $question) {
            $alternatives = json_decode($question->alternatives, true);
            
            if (!$alternatives) {
                continue;
            }

            $isTrueFalse = $this->isTrueFalse($alternatives);
            
            if ($isTrueFalse) {
                DB::table('questions')
                    ->where('id', $question->id)
                    ->update(['format' => 'true_false']);
            }

            foreach ($alternatives as $label => $content) {
                DB::table('question_alternatives')->insert([
                    'question_id' => $question->id,
                    'label' => $label,
                    'content' => $content,
                    'is_correct' => strtoupper($label) === strtoupper($question->correct_answer),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Detect if a question is True/False based on its alternatives.
     */
    private function isTrueFalse(array $alternatives): bool
    {
        if (count($alternatives) !== 2) {
            return false;
        }

        $texts = array_map('strtolower', array_values($alternatives));
        
        $tfMismatches = array_diff($texts, ['certo', 'errado', 'verdadeiro', 'falso', 'true', 'false']);
        
        // If there are no mismatches with common T/F terms, it's T/F
        return count($tfMismatches) === 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('question_alternatives')->truncate();
        DB::table('questions')->update(['format' => 'multiple_choice']);
    }
};
