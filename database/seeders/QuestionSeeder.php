<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // Questões de Matemática
            [
                'type' => 'enem',
                'subject' => 'matemática',
                'theme' => 'Álgebra',
                'difficulty' => 'medium',
                'year' => 2023,
                'statement' => 'Uma empresa de tecnologia possui 120 funcionários. Sabendo que 40% deles trabalham no setor de desenvolvimento, 30% no setor de marketing e o restante no setor administrativo, quantos funcionários trabalham no setor administrativo?',
                'alternatives' => [
                    'A' => '24 funcionários',
                    'B' => '36 funcionários',
                    'C' => '42 funcionários',
                    'D' => '48 funcionários',
                    'E' => '54 funcionários',
                ],
                'correct_answer' => 'B',
                'explanation' => 'Desenvolvimento: 40% de 120 = 48. Marketing: 30% de 120 = 36. Administrativo: 120 - 48 - 36 = 36 funcionários.',
                'source' => 'manual',
            ],
            [
                'type' => 'enem',
                'subject' => 'matemática',
                'theme' => 'Geometria',
                'difficulty' => 'easy',
                'year' => 2023,
                'statement' => 'Um terreno retangular possui 15 metros de largura e 25 metros de comprimento. Qual é a área deste terreno em metros quadrados?',
                'alternatives' => [
                    'A' => '300 m²',
                    'B' => '325 m²',
                    'C' => '350 m²',
                    'D' => '375 m²',
                    'E' => '400 m²',
                ],
                'correct_answer' => 'D',
                'explanation' => 'Área = largura × comprimento = 15 × 25 = 375 m².',
                'source' => 'manual',
            ],

            // Questões de Português
            [
                'type' => 'enem',
                'subject' => 'português',
                'theme' => 'Gramática',
                'difficulty' => 'medium',
                'year' => 2023,
                'statement' => 'Assinale a alternativa que completa corretamente a frase: "Os alunos _______ estudaram para a prova obtiveram boas notas."',
                'alternatives' => [
                    'A' => 'que',
                    'B' => 'quê',
                    'C' => 'o qual',
                    'D' => 'qual',
                    'E' => 'onde',
                ],
                'correct_answer' => 'A',
                'explanation' => 'O pronome relativo "que" é usado para retomar o termo "alunos" e introduzir a oração subordinada.',
                'source' => 'manual',
            ],
            [
                'type' => 'enem',
                'subject' => 'português',
                'theme' => 'Interpretação de texto',
                'difficulty' => 'hard',
                'year' => 2023,
                'statement' => 'Leia o trecho: "A educação é a arma mais poderosa que você pode usar para mudar o mundo." (Nelson Mandela). Qual é a figura de linguagem predominante neste trecho?',
                'alternatives' => [
                    'A' => 'Metáfora',
                    'B' => 'Hipérbole',
                    'C' => 'Eufemismo',
                    'D' => 'Ironia',
                    'E' => 'Antítese',
                ],
                'correct_answer' => 'A',
                'explanation' => 'A metáfora está presente ao comparar a educação a uma arma, atribuindo características de poder e transformação.',
                'source' => 'manual',
            ],
        ];

        // Adicionar mais questões para atingir 200
        $moreQuestions = [];

        // Matemática - 100 questões geradas
        for ($i = 0; $i < 50; $i++) {
            $num1 = rand(10, 100);
            $num2 = rand(10, 100);
            $correctAnswer = $num1 + $num2;

            $moreQuestions[] = [
                'type' => rand() % 2 == 0 ? 'enem' : 'concurso',
                'subject' => 'matemática',
                'theme' => ['Álgebra', 'Geometria', 'Aritmética', 'Probabilidade'][rand(0, 3)],
                'difficulty' => ['easy', 'medium', 'hard'][rand(0, 2)],
                'year' => 2020 + rand(0, 3),
                'statement' => "Calcule a soma de $num1 + $num2.",
                'alternatives' => [
                    'A' => ($correctAnswer - 2) . '',
                    'B' => ($correctAnswer - 1) . '',
                    'C' => $correctAnswer . '',
                    'D' => ($correctAnswer + 1) . '',
                    'E' => ($correctAnswer + 2) . '',
                ],
                'correct_answer' => 'C',
                'explanation' => "A soma de $num1 + $num2 = $correctAnswer.",
                'source' => 'manual',
            ];
        }

        // Português - 100 questões geradas
        $palavras = ['cantar', 'dançar', 'escrever', 'ler', 'estudar', 'trabalhar', 'correr', 'andar'];
        for ($i = 0; $i < 50; $i++) {
            $word = $palavras[array_rand($palavras)];

            $moreQuestions[] = [
                'type' => rand() % 2 == 0 ? 'enem' : 'concurso',
                'subject' => 'português',
                'theme' => ['Gramática', 'Interpretação de texto', 'Literatura', 'Ortografia'][rand(0, 3)],
                'difficulty' => ['easy', 'medium', 'hard'][rand(0, 2)],
                'year' => 2020 + rand(0, 3),
                'statement' => "Qual é a classe gramatical da palavra '$word'?",
                'alternatives' => [
                    'A' => 'Substantivo',
                    'B' => 'Adjetivo',
                    'C' => 'Verbo',
                    'D' => 'Advérbio',
                    'E' => 'Pronome',
                ],
                'correct_answer' => 'C',
                'explanation' => "A palavra '$word' é um verbo, pois indica uma ação.",
                'source' => 'manual',
            ];
        }

        // Questões variadas para completar 200
        for ($i = 0; $i < 96; $i++) {
            $ismath = $i % 2 == 0;

            if ($ismath) {
                $num = rand(2, 12);
                $mult = rand(1, 10);
                $correctAnswer = $num * $mult;

                $moreQuestions[] = [
                    'type' => rand() % 2 == 0 ? 'enem' : 'concurso',
                    'subject' => 'matemática',
                    'theme' => 'Aritmética',
                    'difficulty' => 'easy',
                    'year' => 2020 + rand(0, 3),
                    'statement' => "Quanto é $num × $mult?",
                    'alternatives' => [
                        'A' => ($correctAnswer - 3) . '',
                        'B' => ($correctAnswer - 1) . '',
                        'C' => $correctAnswer . '',
                        'D' => ($correctAnswer + 1) . '',
                        'E' => ($correctAnswer + 3) . '',
                    ],
                    'correct_answer' => 'C',
                    'explanation' => "$num × $mult = $correctAnswer.",
                    'source' => 'manual',
                ];
            } else {
                $palavras2 = ['livro', 'casa', 'carro', 'computador', 'telefone', 'mesa'];
                $word = $palavras2[array_rand($palavras2)];

                $moreQuestions[] = [
                    'type' => rand() % 2 == 0 ? 'enem' : 'concurso',
                    'subject' => 'português',
                    'theme' => 'Gramática',
                    'difficulty' => 'easy',
                    'year' => 2020 + rand(0, 3),
                    'statement' => "Qual é a classe gramatical da palavra '$word'?",
                    'alternatives' => [
                        'A' => 'Substantivo',
                        'B' => 'Adjetivo',
                        'C' => 'Verbo',
                        'D' => 'Advérbio',
                        'E' => 'Pronome',
                    ],
                    'correct_answer' => 'A',
                    'explanation' => "A palavra '$word' é um substantivo, pois nomeia um ser ou objeto.",
                    'source' => 'manual',
                ];
            }
        }

        // Inserir todas as questões
        foreach (array_merge($questions, $moreQuestions) as $question) {
            Question::create($question);
        }

        $this->command->info('200 questões criadas com sucesso!');
    }
}
