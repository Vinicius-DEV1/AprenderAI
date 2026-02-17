<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionGeneratorService
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function isAiReady(): bool
    {
        return $this->aiService->hasActiveKey();
    }

    public function generate(string $banca, bool $includeEssay): array
    {
        $questions = [];

        // Batch 1: Português (30 questions)
        $ptQuestions = $this->generateBatch($banca, 'português', 30);
        $questions = array_merge($questions, $ptQuestions);

        // Batch 2: Matemática (30 questions)
        $matQuestions = $this->generateBatch($banca, 'matemática', 30);
        $questions = array_merge($questions, $matQuestions);

        // Save to Database
        $savedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($questions as $qData) {
                // Ensure correct structure and values
                $qData['type'] = 'concurso';
                $qData['source'] = 'ai_generated';
                $qData['origin'] = 'IA';
                // theme includes banca
                $qData['theme'] = 'banca:' . $banca;

                // Duplication check
                $exists = Question::where('type', 'concurso')
                    ->where('statement', $qData['statement'])
                    ->where('source', 'ai_generated')
                    ->exists();

                if (!$exists) {
                    Question::create($qData);
                    $savedCount++;
                }
            }
            DB::commit();
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save generated questions: " . $e->getMessage());
            throw $e;
        }

        // Generate Essay if requested
        $essayData = null;
        if ($includeEssay) {
            $essayData = $this->generateEssay($banca);
        }

        return [
            'total_generated' => count($questions),
            'total_inserted' => $savedCount,
            'banca' => $banca,
            'essay_generated' => $includeEssay,
            'essay' => $essayData
        ];
    }

    protected function generateBatch(string $banca, string $subject, int $count): array
    {
        $prompt = $this->buildPrompt($banca, $subject, $count);

        // Call AI Service
        $result = $this->aiService->generateJson($prompt);

        // Parse result
        if (empty($result) || !isset($result['data'])) {
            Log::warning("AI returned empty result for batch $subject");
            return [];
        }

        $data = $result['data'];

        if (isset($data['questions'])) {
            $data = $data['questions'];
        }

        if (!is_array($data)) {
            return [];
        }

        // Filter valid questions
        $validQuestions = [];
        foreach ($data as $item) {
            if ($this->isValidQuestion($item)) {
                $item['subject'] = $subject;
                $validQuestions[] = $item;
            }
        }

        return $validQuestions;
    }

    protected function generateEssay(string $banca): ?array
    {
        $prompt = $this->buildEssayPrompt($banca);
        $result = $this->aiService->generateJson($prompt);

        if (!empty($result) && isset($result['data'])) {
            $data = $result['data'];

            // Persist to essays table as requested
            try {
                $user = \App\Models\User::first();
                if ($user) {
                    \App\Models\Essay::create([
                        'user_id' => $user->id,
                        'title' => $data['title'] ?? 'Redação Inédita - ' . $banca,
                        'theme' => 'banca:' . $banca,
                        'content' => ($data['motivational_text'] ?? '') . "\n\n" . ($data['instructions'] ?? ''),
                        'status' => 'pending', // Default status in migration
                    ]);
                }
            }
            catch (\Exception $e) {
                Log::error("Failed to save essay: " . $e->getMessage());
            }

            return $data;
        }
        return null;
    }

    protected function isValidQuestion($item): bool
    {
        // Strict schema validation as requested
        return is_array($item) &&
            !empty($item['statement']) &&
            !empty($item['alternatives']) && is_array($item['alternatives']) &&
            !empty($item['correct_answer']) &&
            isset($item['year']) &&
            !empty($item['difficulty']) &&
            !empty($item['explanation']);
    }

    protected function buildPrompt(string $banca, string $subject, int $count): string
    {
        // Construct the prompt based on the user's STRICT text, but adjusting for batching.
        $subjectLine = $subject === 'português'
            ? "Matemática: 0\nPortuguês: $count"
            : "Matemática: $count\nPortuguês: 0";

        $basePrompt = <<<PROMPT


Você é um especialista em elaboração de questões para concursos públicos brasileiros.

Sua função é gerar questões 100% originais, sem copiar ou adaptar qualquer questão real existente.

IMPORTANTE:
Não utilizar textos, estruturas ou enunciados existentes.
Não reescrever questões conhecidas.
Criar conteúdo totalmente novo.
Manter apenas o perfil estatístico e pedagógico da banca selecionada.

O usuário escolheu a banca: $banca

Você deve modelar o DNA pedagógico da banca escolhida com base nos seguintes critérios:
Estrutura de cobrança
Nível médio de dificuldade
Tipo de raciocínio exigido
Complexidade textual
Tamanho médio do enunciado
Frequência de temas recorrentes
Tipo de pegadinhas comuns
Perfil das alternativas (mais técnicas, mais extensas, mais objetivas etc.)

Configuração padrão do simulado NESTA ETAPA:
Total de questões: $count
$subjectLine

Regras para geração:
Todas as questões devem ser inéditas.
Nenhuma deve se parecer estruturalmente com questão conhecida.
Alternativas devem ser coerentes, plausíveis e técnicas.
Evitar padrões repetitivos.
Manter nível de dificuldade compatível com a banca real.
Incluir explicação técnica detalhada para cada questão.
Distribuir temas conforme frequência real da banca.
Não mencionar que a questão é original ou gerada.

Formato de saída (compatível com banco de dados):
Para cada questão, retornar exatamente no seguinte formato JSON:
{
"type": "concurso",
"subject": "$subject",
"theme": "banca:$banca",
"source": "ai_generated",
"year": ano fictício coerente entre 2015 e 2025,
"statement": "enunciado completo e inédito",
"alternatives": {
"A": "alternativa A",
"B": "alternativa B",
"C": "alternativa C",
"D": "alternativa D",
"E": "alternativa E"
},
"correct_answer": "A ou B ou C ou D ou E",
"difficulty": "easy ou medium ou hard",
"explanation": "explicação técnica detalhada da resposta correta"
}
Não adicionar texto fora do JSON.
Não incluir comentários.
Não incluir títulos.
Não incluir separadores.

Gerar exatamente $count objetos JSON.
PROMPT;

        return $basePrompt;
    }

    protected function buildEssayPrompt(string $banca): string
    {
        return <<<PROMPT


Você é um especialista em concursos.
Gere uma proposta de redação inédita para a banca $banca.

Formato JSON esperado:
{
"type": "essay",
"theme": "banca:$banca",
"title": "tema da redação",
"motivational_text": "texto motivador inédito",
"instructions": "comando claro ao candidato",
"evaluation_criteria": [
"Competência 1",
"Competência 2",
"Competência 3",
"Competência 4",
"Competência 5"
]
}
Não adicionar texto fora do JSON.
PROMPT;
    }
}
