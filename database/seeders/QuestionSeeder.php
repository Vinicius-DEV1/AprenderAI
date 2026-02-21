<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        // Documentação Viva: Seed enxuto para fins de testes rápidos e exemplos de estrutura relacional N:N.
        // Carrega as questões muito mais rápido (não gera centenas pseudo-aleatórias)
        
        // Garante a existência das Matérias padronizadas (Upper Case + slug)
        // Isso evita "Matemática" vs "MATEMÁTICA", unificando entidades.
        $portSubject = Subject::firstOrCreate(
            ['name' => trim(strtoupper('PORTUGUÊS'))],
            ['slug' => Str::slug('PORTUGUÊS'), 'type' => 'geral']
        );
        
        $mathSubject = Subject::firstOrCreate(
            ['name' => trim(strtoupper('MATEMÁTICA'))],
            ['slug' => Str::slug('MATEMÁTICA'), 'type' => 'geral']
        );

        // Garante a existência dos Assuntos nas respectivas matérias
        $portTopic = Topic::firstOrCreate(
            ['name' => trim(strtoupper('INTERPRETAÇÃO DE TEXTOS'))],
            ['slug' => Str::slug('INTERPRETAÇÃO DE TEXTOS')]
        );

        $mathTopic = Topic::firstOrCreate(
            ['name' => trim(strtoupper('GEOMETRIA PLANA'))],
            ['slug' => Str::slug('GEOMETRIA PLANA')]
        );

        // --- QUESTÃO 1: ENEM ---
        $this->createQuestion(
            type: 'enem',
            organization: 'ENEM',
            year: 2023,
            institution: 'MEC',
            role: 'Estudante',
            statement: "Texto I: O hábito da leitura na era digital...\n\nQual o objetivo central do texto ao mencionar as redes sociais?",
            subjects: [$portSubject->id],
            topics: [$portTopic->id],
            alternatives: [
                'A' => 'Incentivar o uso exclusivo do papel impresso.',
                'B' => 'Destacar como a leitura migrou das páginas físicas para os feeds virtuais.',
                'C' => 'Criticar a ortografia usada pelos adolescentes.',
                'D' => 'Desencorajar o estudo pelo meio literário.',
                'E' => 'Ignorar a velocidade de propagação de notícias.'
            ],
            correctLetter: 'B',
            explanation: 'A alternativa B resume corretamente o escopo de adaptação digital citado na questão.',
            theme: 'Linguagens, Códigos e suas Tecnologias' // Mantido exclusivamente para Eixo Temático ENEM
        );

        // --- QUESTÃO 2: CONCURSO ---
        $this->createQuestion(
            type: 'concurso',
            organization: 'CEBRASPE',
            year: 2024,
            institution: 'Polícia Federal',
            role: 'Agente Administrativo',
            statement: "Calcule a área de um paralelogramo cuja base mede 10cm e altura corresponde a metade da base.",
            subjects: [$mathSubject->id],
            topics: [$mathTopic->id],
            alternatives: [
                'A' => '25 cm²',
                'B' => '50 cm²',
                'C' => '75 cm²',
                'D' => '100 cm²',
                'E' => '150 cm²'
            ],
            correctLetter: 'B',
            explanation: 'Sendo h = 10/2 = 5cm. A área é b(base) * h(altura) = 10 * 5 = 50 cm².',
            theme: null // Concursos usam banco de topics/subjects robusto e não herdam "Eixos Temáticos"
        );
        
        $this->command->info('QuestionSeeder: Documentação Viva gerada com sucesso (Velocidade otimizada: 2 questões com N:N).');
    }

    /**
     * Auxiliar de inserção para documentar as etapas de popular a estrutura relacional.
     */
    private function createQuestion($type, $organization, $year, $institution, $role, $statement, $subjects, $topics, $alternatives, $correctLetter, $explanation, $theme)
    {
        // 1. Geração de Identificador Idempotente (Evita Duplicadas)
        // A external_id é uma hash MD5 de atributos chave que compõem a identidade única macro da questão.
        $uniqueString = $organization . '|' . $year . '|' . $institution . '|' . $role . '|' . trim($statement);
        $externalId = md5($uniqueString);

        // 2. Inserção na Tabela Base (questions)
        // As colunas legadas (origin, topic original string, alternativas em JSON bruto) NÃO DEVEM mais ser imputadas.
        $question = clone Question::updateOrCreate(
            ['external_id' => $externalId],
            [
                'type' => $type,
                'difficulty' => 'medium',
                'difficulty_reasoning' => 'Dificuldade avaliada pelo sistema central ou IA.',
                'year' => $year,
                'statement' => $statement,
                'explanation' => $explanation,
                'source' => 'manual', // Manual no sentido de seed/painel, e não crawler web
                'theme' => $theme,    // IMPORTANTE: Preservado unicamente como repositório de metadado orgânico de Eixo ENEM.
                'organization' => $organization,
                'institution' => $institution,
                'role' => $role,
                'review_status' => 'approved',
            ]
        );

        // 3. Relacionamentos N:N (Pivot Tables)
        // O método sync() atrela os IDs à tabela pivô e desataca o restante, mantendo referencial imaculado aos Subjects.
        $question->subjects()->sync($subjects);
        $question->topics()->sync($topics);

        // 4. Inserção de Alternativas (HasMany)
        // Remove as anteriores em caso de update do Seeder, e re-popula as opções.
        $question->alternatives()->delete();
        
        $altsToInsert = [];
        foreach ($alternatives as $label => $content) {
            $altsToInsert[] = [
                'label' => $label,
                'content' => $content,
                'is_correct' => ($label === $correctLetter),
            ];
        }
        
        // Injeção de registros 1:N no modelo relacional question_alternatives
        $question->alternatives()->createMany($altsToInsert);
    }
}
