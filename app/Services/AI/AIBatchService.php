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
     * PONTO DE ENTRADA: Processa um lote de questões.
     * 
     * @param Collection<Question> $questions - Coleção de modelos Question do Laravel.
     * @param string $type - 'difficulty' (só dificuldade), 'explanation' (só explicação) ou 'both' (ambos).
     * @param string|null $model - Nome do modelo de IA (ex: 'gemini-1.5-flash').
     * @return array - Estatísticas do processamento (total, aplicados, erros).
     */
    public function processBatch(Collection $questions, string $type, ?string $model = null): array
    {
        // 1. Constrói o prompt estruturado com os dados das questões
        $prompt = $this->buildBatchPrompt($questions, $type);

        try {
            // 2. Chama o AIService que lida com as chaves e a API da IA escolhida
            // Esperamos um JSON estruturado como resposta
            $result = $this->aiService->generateJson($prompt, $model);
            
            $data = $result['data'] ?? [];
            
            // 3. Aplica os resultados retornados pela IA no Banco de Dados
            return $this->applyResults($questions, $data, $type);
        } catch (\Exception $e) {
            Log::error("AIBatchService: Falha no processamento do lote: " . $e->getMessage(), [
                'batch_ids' => $questions->pluck('id')->toArray()
            ]);
            throw $e;
        }
    }

    /**
     * CONSTRUÇÃO DO PROMPT: Prepara o terreno para a IA.
     * 
     * Mapeamos os campos essenciais das questões para JSON para que a IA 
     * processe várias de uma vez, otimizando custo e tempo.
     */
    protected function buildBatchPrompt(Collection $questions, string $type): string
    {
        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'statement' => $q->statement,
                'alternatives' => $q->alternativesAsMap(), // Helper que entrega A->Texto, B->Texto...
                'correct_label' => $q->correct_answer
            ];
        });

        // Define a instrução específica baseada na escolha do usuário no modal
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
        
        REGRAS DE RETORNO (CRITICAL):
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
        3. Mantenha os IDs originais rigorosamente para que possamos mapear de volta.
        4. O JSON deve ser puro, sem blocos de código Markdown ou textos extras.";
    }

    /**
     * APLICAÇÃO DOS RESULTADOS: Persistência no banco de dados.
     * 
     * Percorre os dados retornados pela IA e atualiza cada modelo correspondente.
     */
    protected function applyResults(Collection $questions, array $results, string $type): array
    {
        $applied = 0;
        $errors = [];

        // Normalização manual: se a IA retornar um objeto único em vez de lista, envolvemos em array
        if (!is_array($results) || (count($results) > 0 && !isset($results[0]))) {
             $results = [$results];
        }

        foreach ($questions as $question) {
            // Busca os dados específicos para esta questão no retorno da IA
            $data = collect($results)->firstWhere('id', $question->id);

            if (!$data) {
                $errors[] = "Questão {$question->id}: Não encontrada no retorno da IA.";
                continue;
            }

            $update = [];
            
            // Atualiza Dificuldade se solicitado
            if (in_array($type, ['difficulty', 'both']) && isset($data['difficulty'])) {
                $update['difficulty'] = $data['difficulty'];
                $update['difficulty_reasoning'] = $data['difficulty_reasoning'] ?? null;
            }

            // Atualiza Explicação se solicitada
            if (in_array($type, ['explanation', 'both']) && isset($data['explanation'])) {
                $update['explanation'] = $data['explanation'];
            }

            // Persiste apenas se houver mudanças válidas
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
