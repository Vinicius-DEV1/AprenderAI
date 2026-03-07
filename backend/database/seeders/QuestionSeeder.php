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

        // Garante a existência dos Assuntos (Topics)
        // AVISO CRÍTICO: NUNCA passe 'subject_id' na criação do Topic abaixo.
        // O relacionamento é N:N, mantido 100% pelas tabelas pivô na etapa final do Seeder.
        $portTopic = Topic::firstOrCreate(
            ['name' => trim(strtoupper('INTERPRETAÇÃO DE TEXTOS'))],
            ['slug' => Str::slug('INTERPRETAÇÃO DE TEXTOS')]
        );

        $mathTopic = Topic::firstOrCreate(
            ['name' => trim(strtoupper('GEOMETRIA PLANA'))],
            ['slug' => Str::slug('GEOMETRIA PLANA')]
        );

        // --- QUESTÃO 1: ENEM ---
        // Questões enem não precisam de organization, instituion, role.
        $this->createQuestion(
            type: 'enem',
            format: 'multiple_choice',
            //organization: 'ENEM',
            year: 2023,
            //institution: 'MEC',
            //role: 'Estudante',
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
            format: 'multiple_choice',
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
            theme: null
        );

        // --- QUESTÃO 3: CONCURSO (CERTO OU ERRADO) ---
        // Este formato ignora a lógica de 5 alternativas (A-E) e foca na validação binária do formato Concurso/Cebraspe.
        $adminSubject = Subject::firstOrCreate(
            ['name' => trim(strtoupper('DIREITO ADMINISTRATIVO'))],
            ['slug' => Str::slug('DIREITO ADMINISTRATIVO'), 'type' => 'geral']
        );

        $actsTopic = Topic::firstOrCreate(
            ['name' => trim(strtoupper('ATOS ADMINISTRATIVOS'))],
            ['slug' => Str::slug('ATOS ADMINISTRATIVOS')]
        );

        $this->createQuestion(
            type: 'concurso',
            format: 'true_false',
            organization: 'CEBRASPE',
            year: 2024,
            institution: 'ANATEL',
            role: 'Especialista em Regulação',
            statement: "A respeito dos atributos dos atos administrativos, julgue o item a seguir.\n\nA imperatividade é o atributo pelo qual os atos administrativos se impõem a terceiros, independentemente de sua concordância.",
            subjects: [$adminSubject->id],
            topics: [$actsTopic->id],
            alternatives: [
                'C' => 'Certo',
                'E' => 'Errado'
            ],
            correctLetter: 'C',
            explanation: 'A imperatividade é, de fato, o atributo que permite a imposição do ato administrativo a terceiros sem necessidade de anuência prévia.',
            theme: null
        );

        // --- QUESTÃO 4: PENDENTE DE TRIAGEM (TESTE) ---
        $incompleteQuestion = Question::create([
            'type' => 'enem',
            'format' => 'multiple_choice',
            'statement' => 'QUESTÃO DE TESTE: Esta questão deve aparecer na triagem porque não tem explicação nem dificuldade.',
            'year' => 2024,
            'source' => 'ai_generated',
            'organization' => 'TESTE IA',
            'external_id' => 'incomplete_test_001',
            'review_status' => 'pending',
            'difficulty' => 'medium',
            'difficulty_reasoning' => 'Classificada como incompleta para fins de triagem pedagógica manual.',
            'explanation' => null,
        ]);

        $this->command->info('QuestionSeeder: Documentação Viva gerada com sucesso (Incluindo questão incompleta para Triagem).');
    }

    /**
     * Auxiliar de inserção para documentar as etapas de popular a estrutura relacional.
     */
    private function createQuestion($type, $format, $organization = null, $year = null, $institution = null, $role = null, $statement = null, $subjects = [], $topics = [], $alternatives = [], $correctLetter = null, $explanation = null, $theme = null)
    {
        // 1. Geração de Identificador Idempotente (Evita Duplicadas)
        // A external_id é uma hash MD5 de atributos chave que compõem a identidade única macro da questão.
        $uniqueString = $organization . '|' . $year . '|' . $institution . '|' . $role . '|' . trim($statement);
        $externalId = md5($uniqueString);

        // 2. Inserção na Tabela Base (questions)
        $question = Question::updateOrCreate(
            ['external_id' => $externalId],
            [
                'type' => $type,
                'format' => $format,
                'difficulty' => 'medium',
                'difficulty_reasoning' => 'A questão exige análise interpretativa de nível intermediário, focando na identificação de teses centrais e na distinção entre fatos e opiniões no texto.',
                'year' => $year,
                'statement' => $statement,
                'explanation' => $explanation,
                'source' => 'manual',
                'theme' => $theme,
                'organization' => $organization,
                'institution' => $institution,
                'role' => $role,
                'review_status' => 'approved',
            ]
        );

        // 3. Relacionamentos N:N (Pivot Tables)
        $question->subjects()->sync($subjects);
        $question->topics()->sync($topics);

        // 4. Inserção de Alternativas (HasMany)
        $question->alternatives()->delete();

        $altsToInsert = [];
        foreach ($alternatives as $label => $content) {
            $altsToInsert[] = [
                'label' => $label,
                'content' => $content,
                'is_correct' => ($label === $correctLetter),
            ];
        }

        $question->alternatives()->createMany($altsToInsert);
    }
}
