<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DiscursiveAndEssayMockSeeder extends Seeder
{
    public function run(): void
    {
        // Certificar que ao menos uma matéria (Subject) exista
        $subject = Subject::firstOrCreate(['name' => 'Direito Penal', 'slug' => 'direito-penal']);

        // === 1. Questão Discursiva Mock ===
        $discursiveId = md5('mock_discursive_1' . time());

        $qDiscursive = Question::create([
            'external_id' => $discursiveId,
            'tipo_questao' => 'Discursiva',
            'number' => '1',
            'type' => 'concurso',
            'format' => 'discursiva',
            'difficulty' => 'hard',
            'year' => 2024,
            'statement' => "João, funcionário público, solicitou vantagem indevida a Mário para acelerar o andamento de um processo administrativo. Mário recusou e denunciou João. Considerando este cenário, responda aos itens a seguir com base no Código Penal Brasileiro.",
            'source' => 'Simulado Mock',
            'organization' => 'Banca AI',
            'arquivo_origem' => 'prova_mock_direito.pdf',
            'discursive_answer' => [
                'a' => 'O crime configurado é o de Corrupção Passiva, previsto no art. 317 do CP, pois João "solicitou" vantagem indevida em razão da função.',
                'b' => 'Não houve crime por parte de Mário, pois ele recusou a solicitação. A corrupção ativa (art. 333) exigiria que ele oferecesse ou prometesse a vantagem.'
            ],
            'review_status' => 'approved' // Para aparecer no frontend público
        ]);

        $qDiscursive->subjects()->sync([$subject->id]);

        // Criar as alternativas (subitens A e B)
        QuestionAlternative::create([
            'question_id' => $qDiscursive->id,
            'label' => 'a',
            'content' => 'Qual o crime cometido por João? Fundamente legalmente.',
            'is_correct' => false
        ]);

        QuestionAlternative::create([
            'question_id' => $qDiscursive->id,
            'label' => 'b',
            'content' => 'A conduta de Mário configura algum crime? Justifique.',
            'is_correct' => false
        ]);


        // === 2. Questão Redação Mock ===
        $redacaoSubject = Subject::firstOrCreate(['name' => 'Redação', 'slug' => 'redacao']);
        $essayId = md5('mock_essay_1' . time());

        $qEssay = Question::create([
            'external_id' => $essayId,
            'tipo_questao' => 'Redação',
            'number' => 'Redação',
            'type' => 'concurso',
            'format' => 'redacao',
            'difficulty' => 'medium',
            'year' => 2024,
            'statement' => "### Texto 1\n\"A inteligência artificial tem impactado significativamente as relações de trabalho...\"\n\n### Texto 2\n\"Por outro lado, muitos temem a automação crônica...\"\n\n**PROPOSTA DE REDAÇÃO:**\nCom base na leitura dos textos motivadores e nos seus conhecimentos, redija um texto dissertativo-argumentativo sobre o tema: **Os impactos morais e profissionais da Inteligência Artificial no mercado de trabalho global**.",
            'source' => 'Simulado Mock',
            'organization' => 'Banca AI',
            'arquivo_origem' => 'prova_mock_redacao.pdf',
            'discursive_answer' => "Espera-se que o candidato produza um texto na norma-padrão da língua portuguesa, estruturado com Introdução, Desenvolvimento e Conclusão. Na argumentação, deve abordar pontos como: desemprego vs. criação de novas profissões; necessidade de regulamentação ética; limites morais do uso da IA em decisões críticas.",
            'review_status' => 'approved'
        ]);

        $qEssay->subjects()->sync([$redacaoSubject->id]);

        // Redação não possui alternativas na tabela child
    }
}
