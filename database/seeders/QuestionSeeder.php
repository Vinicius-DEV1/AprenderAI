<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class QuestionSeeder extends Seeder
{
    private $faker;

    public function run(): void
    {
        $this->faker = Faker::create('pt_BR');

        // Clean previous generated questions
        Question::where('source', 'generated_system')->delete();

        // Ensure subjects exist
        $portSubject = Subject::where('name', 'Português')->first();
        $mathSubject = Subject::where('name', 'Matemática')->first();

        if (!$portSubject || !$mathSubject) {
            $this->command->error('Subjects "Português" or "Matemática" not found. Run SubjectSeeder first.');
            return;
        }

        // Generate Português
        $this->generatePortuguese($portSubject);

        // Generate Matemática
        $this->generateMath($mathSubject);

        $this->command->info('Questões geradas com sucesso!');
    }

    private function generatePortuguese($subject)
    {
        $templates = $this->getPortugueseTemplates();

        foreach ($templates as $i => $tpl) {
            $question = Question::create([
                'type' => 'enem',
                'theme' => $tpl['theme'],
                'difficulty' => $tpl['difficulty'] ?? 'medium',
                'year' => $this->faker->numberBetween(2024, 2026),
                'statement' => $tpl['statement'],
                'alternatives' => $tpl['alternatives'],
                'correct_answer' => $tpl['correct_answer'],
                'explanation' => $tpl['explanation'],
                'difficulty_reasoning' => $tpl['difficulty_reasoning'] ?? 'Esta questão avalia competências básicas de interpretação.',
                'source' => 'generated_system',
            ]);

            $question->subjects()->attach($subject->id);
        }
    }

    private function generateMath($subject)
    {
        $templates = $this->getMathTemplates();

        foreach ($templates as $i => $tpl) {
            $question = Question::create([
                'type' => 'enem',
                'theme' => $tpl['theme'],
                'difficulty' => $tpl['difficulty'] ?? 'medium',
                'year' => $this->faker->numberBetween(2024, 2026),
                'statement' => $tpl['statement'],
                'alternatives' => $tpl['alternatives'],
                'correct_answer' => $tpl['correct_answer'],
                'explanation' => $tpl['explanation'],
                'difficulty_reasoning' => $tpl['difficulty_reasoning'] ?? 'Esta questão exige raciocínio lógico e aplicação de fórmulas.',
                'source' => 'generated_system',
            ]);

            $question->subjects()->attach($subject->id);
        }
    }

    private function getPortugueseTemplates()
    {
        $data = [];

        // TEMPLATE 1: Poesia e Interpretação
        $data[] = [
            'theme' => 'Interpretação de Texto',
            'statement' => "TEXTO I\n\nNo meio do caminho tinha uma pedra\ntinha uma pedra no meio do caminho\ntinha uma pedra\nno meio do caminho tinha uma pedra.\n\n(Carlos Drummond de Andrade)\n\nTEXTO II\n\nA repetição vocabular presente no poema de Drummond não é sinal de pobreza lexical, mas um recurso estilístico que:",
            'alternatives' => [
                'A' => 'Reforça a monotonia da caminhada e a onipresença do obstáculo.',
                'B' => 'Demonstra a falta de criatividade do eu-lírico diante dos problemas.',
                'C' => 'Sugere que a pedra é um objeto irrelevante na vida do poeta.',
                'D' => 'Critica a estrutura das estradas brasileiras na década de 30.',
                'E' => 'Ignora as regras gramaticais de coesão e coerência.',
            ],
            'correct_answer' => 'A',
            'explanation' => 'A repetição enfatiza a obsessão e a dificuldade de superar o obstáculo (a pedra).',
        ];

        // TEMPLATE 2: Variação Linguística
        $data[] = [
            'theme' => 'Variação Linguística',
            'statement' => "TEXTO I\n\n- E aí, mano? Tamo junto na fita?\n- Demorou, truta! É nóis que voa.\n\nO diálogo acima, típico de determinados grupos sociais urbanos, exemplifica o uso de uma variedade linguística que:",
            'alternatives' => [
                'A' => 'Deve ser banida da escrita por ser incorreta gramaticalmente.',
                'B' => 'Demonstra pobreza de vocabulário dos falantes.',
                'C' => 'Atua como marcador de identidade e coesão grupal.',
                'D' => 'Impede a comunicação clara entre os interlocutores.',
                'E' => 'Revela incapacidade de usar a norma culta em qualquer contexto.',
            ],
            'correct_answer' => 'C',
            'explanation' => 'As gírias funcionam como identidade de grupo.',
        ];

        $themes = ['Interpretação', 'Gêneros Textuais', 'Literatura', 'Gramática Aplicada', 'Artes'];

        for ($i = 0; $i < 20; $i++) {
            $topic = $this->faker->randomElement($themes);
            $author = $this->faker->name;
            $data[] = [
                'theme' => $topic,
                'statement' => "TEXTO I\n\nA cultura digital transformou o modo como lemos e escrevemos. Segundo {$author}, \"a hipertextualidade permite uma leitura não linear, exigindo do leitor maior autonomia\". Diante desse cenário, a plataforma aprenderAI oferece recursos que auxiliam a escola no desafio de:",
                'alternatives' => [
                    'A' => 'Proibir o uso de tecnologias para focar na leitura tradicional.',
                    'B' => 'Integrar o letramento digital às práticas pedagógicas convencionais.',
                    'C' => 'Substituir livros físicos exclusivamente por tablets.',
                    'D' => 'Ignorar a cultura digital, pois ela é passageira.',
                    'E' => 'Limitar o acesso à informação para evitar dispersão.',
                ],
                'correct_answer' => 'B',
                'explanation' => 'A integração é a resposta pedagógica adequada à cultura digital.',
            ];
        }

        return $data;
    }

    private function getMathTemplates()
    {
        $data = [];

        // TEMPLATE 1: Porcentagem
        $val = $this->faker->numberBetween(100, 500);
        $data[] = [
            'theme' => 'Porcentagem',
            'statement' => "Um produto custava R$ {$val},00 e teve um aumento de 20%. Em seguida, devido à baixa, teve um desconto de 20% sobre o novo valor. O preço final é:",
            'alternatives' => [
                'A' => "Igual a R$ {$val},00",
                'B' => "Menor que R$ {$val},00",
                'C' => "Maior que R$ {$val},00",
                'D' => "R$ " . ($val * 1.1),
                'E' => "R$ " . ($val * 0.9),
            ],
            'correct_answer' => 'B',
            'explanation' => 'Aumento de 20% = 1.2x. Desconto de 20% = 0.8x. Final = 1.2 * 0.8 = 0.96x (96% do original), logo menor.',
        ];

        for ($i = 0; $i < 20; $i++) {
            $a = $this->faker->numberBetween(2, 10);
            $b = $this->faker->numberBetween(10, 50);
            $ans = $a * $b;

            $data[] = [
                'theme' => 'Aritmética',
                'statement' => "Em um estoque de materiais do aprenderAI, há {$a} caixas, e cada caixa contém {$b} unidades de um fanzine educacional. Se forem vendidas 10% das unidades totais, quantas restarão?",
                'alternatives' => [
                    'A' => ($ans * 0.9),
                    'B' => ($ans * 0.1),
                    'C' => ($ans - 10),
                    'D' => ($ans / 2),
                    'E' => ($ans),
                ],
                'correct_answer' => 'A',
                'explanation' => "Total = {$a} * {$b} = {$ans}. Restam 90%, ou seja, {$ans} * 0.9.",
            ];
        }

        return $data;
    }
}
