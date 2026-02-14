<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;

class AddPortugueseQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 200; $i++) {
            Question::create([
                'type' => 'enem',
                'subject' => 'português',
                'theme' => 'Interpretação de texto',
                'difficulty' => 'medium',
                'year' => 2026,
                'statement' => "Questão simulada de Português #{$i}: leia o texto (simulado) e responda à pergunta (simulada).",
                'alternatives' => [
                    'A' => "Alternativa A (simulada) #{$i}",
                    'B' => "Alternativa B (simulada) #{$i}",
                    'C' => "Alternativa C (simulada) #{$i}",
                    'D' => "Alternativa D (simulada) #{$i}",
                    'E' => "Alternativa E (simulada) #{$i}",
                ],
                'correct_answer' => 'A',
                'explanation' => "Explicação simulada #{$i}: justificativa curta do porquê a alternativa A é correta.",
                // 'source' => ...  // NÃO setar: deixa o default do DB
            ]);
        }
    }
}