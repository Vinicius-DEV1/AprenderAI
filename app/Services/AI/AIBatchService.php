<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\Question;
use App\Services\AIService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AIBatchService
{
    protected $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Processes a batch of questions using the requested type and model.
     *
     * @param Collection<Question> $questions
     * @param string $type ('difficulty', 'explanation', 'both')
     * @param string|null $model
     * @return array
     */
    public function processBatch(Collection $questions, string $type, ?string $model = null): array
    {
        $prompt = $this->buildBatchPrompt($questions, $type);

        try {
            $result = $this->aiService->generateJson($prompt, $model);
            $data = $result['data'] ?? [];
            return $this->applyResults($questions, $data, $type);
        } catch (\Exception $e) {
            Log::error("Batch AI Processing failed: " . $e->getMessage(), [
                'batch_ids' => $questions->pluck('id')->toArray()
            ]);
            throw $e;
        }
    }

    protected function buildBatchPrompt(Collection $questions, string $type): string
    {
        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'statement' => $q->statement,
                'alternatives' => $q->alternativesAsMap(),
                'correct_label' => $q->correct_answer
            ];
        });

        $instruction = match ($type) {
            'difficulty' => "Avalie APENAS a dificuldade (easy, medium, hard) e forneça um raciocínio curto.",
            'explanation' => "Gere APENAS uma explicação pedagógica clara para a alternativa correta.",
            'both' => "Avalie a dificuldade (easy, medium, hard) com raciocínio E gere uma explicação pedagógica.",
        };

        return "Atue como um Especialista em Educação e IA. 
        Preciso que você processe o seguinte lote de questões do ENEM/Concursos.
        
        TAREFA: {$instruction}
        
        DADOS (JSON):
        " . json_encode($questionsData) . "
        
        REGRAS DE RETORNO:
        1. Responda APENAS com um array JSON no formato:
           [
             {
               \"id\": ID_DA_QUESTAO,
               \"difficulty\": \"easy|medium|hard\",
               \"difficulty_reasoning\": \"Sua justificativa curta...\",
               \"explanation\": \"Sua explicação pedagógica...\"
             }
           ]
        2. Se um campo não foi solicitado (ex: explicação quando o tipo é 'difficulty'), retorne-o como null.
        3. Mantenha os IDs originais rigorosamente.
        4. O JSON deve ser válido e sem textos explicativos fora do array.";
    }

    protected function applyResults(Collection $questions, array $results, string $type): array
    {
        $applied = 0;
        $errors = [];

        // Manual validation: ensure it's a list
        if (!is_array($results)) {
             $results = [$results]; // Wrap if it's a single object
        }

        foreach ($questions as $question) {
            $data = collect($results)->firstWhere('id', $question->id);

            if (!$data) {
                $errors[] = "Questão {$question->id}: Não encontrada no retorno da IA.";
                continue;
            }

            $update = [];
            if (in_array($type, ['difficulty', 'both']) && isset($data['difficulty'])) {
                $update['difficulty'] = $data['difficulty'];
                $update['difficulty_reasoning'] = $data['difficulty_reasoning'] ?? null;
            }

            if (in_array($type, ['explanation', 'both']) && isset($data['explanation'])) {
                $update['explanation'] = $data['explanation'];
            }

            if (!empty($update)) {
                $question->update($update);
                $applied++;
            }
        }

        return [
            'total' => $questions->count(),
            'applied' => $applied,
            'errors' => $errors
        ];
    }
}
