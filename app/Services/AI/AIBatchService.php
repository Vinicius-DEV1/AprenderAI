<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        // 1. Constrói o prompt estruturado com os dados ESPECÍFICOS faltantes das questões
        $prompt = $this->buildBatchPrompt($questions, $type);

        try {
            // 2. Chama o AIService que lida com as chaves e a API da IA escolhida
            $result = $this->aiService->generateJson($prompt, $model);

            $data = $result['data'] ?? [];

            // 3. Aplica os resultados retornados pela IA no Banco de Dados
            $appliedData = $this->applyResults($questions, $data, $type);

            // 4. Gestão de Memória (Garbage Collection): limpa query_logs acumulados do chunk
            // Vital para não estourar os limites de RAM do Docker ao processar +200 itens em Background
            \Illuminate\Support\Facades\DB::flushQueryLog();
            if (gc_enabled())
                gc_collect_cycles();

            return $appliedData;
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
            $missingFields = [];
            if (empty($q->difficulty_reasoning) || empty($q->difficulty))
                $missingFields[] = 'difficulty';
            if (empty($q->explanation))
                $missingFields[] = 'explanation';
            if ($q->subjects()->count() === 0)
                $missingFields[] = 'subject';
            if ($q->topics()->count() === 0)
                $missingFields[] = 'topic';

            return [
                'id' => $q->id,
                'statement' => $q->statement,
                'alternatives' => $q->alternativesAsMap(),
                'correct_label' => $q->correct_answer,
                'missing_fields' => $missingFields // Inteligência de Lote: o que a IA deve gerar
            ];
        });

        // Busca referências de IDs para curadoria da IA
        $subjectsRef = json_encode(Subject::pluck('name', 'id')->toArray());
        $topicsRef = json_encode(Topic::pluck('name', 'id')->toArray());

        // Define a instrução específica baseada na escolha do usuário no modal
        $instruction = match ($type) {
            'difficulty' => "Avalie a dificuldade (easy, medium, hard), forneça um raciocínio curto.",
            'explanation' => "Gere uma explicação pedagógica clara e completa do porquê a resposta correta é a correta.",
            'classification' => "Analise a questão e tente mapeá-la para os IDs existentes na lista de referência. Caso a questão trate de um tema que absolutamente não se encaixa em nenhuma das opções fornecidas, você deve sugerir um novo NOME em texto ('string') para a Disciplina ou Assunto. Atenção: Seja criterioso para não criar sinônimos de categorias que já existem.",
            'complete' => "Avalie a dificuldade (com raciocínio), gere uma explicação pedagógica e classifique a questão mapeando para os IDs existentes ou sugerindo um novo Nome em string caso não exista, evitando sinônimos.",
            'both' => "Avalie a dificuldade com raciocínio, gere uma explicação pedagógica, e classifique a Disciplina (Subject) e o Assunto (Topic).", // Fallback legacy
        };

        return "Atue como um Especialista em Educação, IA e Curador de Conteúdo. 
        Preciso que você processe o seguinte lote de questões do ENEM/Concursos.
        
        TAREFA: {$instruction}
        
        ==============
        LISTAS DE REFERÊNCIA PARA CLASSIFICAÇÃO:
        ---
        MATÉRIAS (Subject): 
        {$subjectsRef}
        ---
        ASSUNTOS (Topic):
        {$topicsRef}
        ==============

        DADOS (JSON):
        " . json_encode($questionsData) . "
        
        REGRAS DE RETORNO (CRITICAL):
        1. Responda APENAS com um array JSON no formato:
           [
             {
               \"id\": ID_DA_QUESTAO,
               \"difficulty\": \"easy|medium|hard\", // Gerar APENAS se listado em 'missing_fields'
               \"difficulty_reasoning\": \"Sua justificativa...\", // Gerar APENAS se listado em 'missing_fields'
               \"explanation\": \"Sua explicação...\", // Gerar APENAS se listado em 'missing_fields'
               \"subject\": 12, // ID numérico ou stringnova. Gerar APENAS se listado em 'missing_fields'
               \"topic\": 45 // ID numérico ou stringnova. Gerar APENAS se listado em 'missing_fields'
             }
           ]
        2. Se um campo não está no array 'missing_fields' da questão analisada, RETORNE NULO SEMPRE, pois não é necessário e poupa tempo/tokens.
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

            // Regra de Ouro: Blindagem contra Sobrescrita (Data Safety)
            // Só atualiza 'difficulty'/'difficulty_reasoning' se a questão base estiver vazia
            if (in_array($type, ['difficulty', 'complete', 'both']) && isset($data['difficulty'])) {
                if (empty($question->difficulty) || empty($question->difficulty_reasoning)) {
                    $update['difficulty'] = $data['difficulty'];
                    $update['difficulty_reasoning'] = $data['difficulty_reasoning'] ?? null;
                }
            }

            // Só atualiza 'explanation' se estiver vazia
            if (in_array($type, ['explanation', 'complete', 'both']) && !empty($data['explanation'])) {
                if (empty($question->explanation) || trim($question->explanation) === '') {
                    $update['explanation'] = $data['explanation'];
                }
            }

            // Persiste apenas se houver mudanças válidas (para o model Question base)
            if (!empty($update)) {
                $question->update($update);
            }

            // Lógica N:N (Pivot) + Curadoria de Taxonomia
            // Permitido para 'classification', 'complete', ou fallback 'both'
            if (in_array($type, ['classification', 'complete', 'both'])) {

                // SUBJECT: Restringe sobrescrita. Só aplica IA se não tiver taxonomia vinculada.
                if ($question->subjects()->count() === 0 && isset($data['subject']) && $data['subject'] !== null) {
                    $subjectVal = $data['subject'];
                    if (is_numeric($subjectVal)) {
                        $question->subjects()->syncWithoutDetaching([(int) $subjectVal]);
                    } elseif (is_string($subjectVal) && trim($subjectVal) !== '') {
                        $subjectModel = Subject::firstOrCreate(
                            ['name' => trim($subjectVal)],
                            ['slug' => Str::slug($subjectVal), 'type' => $question->type ?? 'enem']
                        );
                        $question->subjects()->syncWithoutDetaching([$subjectModel->id]);
                    }
                }

                // TOPIC: Restringe sobrescrita. Só aplica IA se não tiver tópico vinculado.
                if ($question->topics()->count() === 0 && isset($data['topic']) && $data['topic'] !== null) {
                    $topicVal = $data['topic'];
                    if (is_numeric($topicVal)) {
                        $question->topics()->syncWithoutDetaching([(int) $topicVal]);
                    } elseif (is_string($topicVal) && trim($topicVal) !== '') {
                        $topicModel = Topic::firstOrCreate(
                            ['name' => trim($topicVal)],
                            ['slug' => Str::slug($topicVal)]
                        );
                        $question->topics()->syncWithoutDetaching([$topicModel->id]);
                    }
                }
            }

            // Marca status principal como analisado/aprovado
            $question->update(['review_status' => 'approved']);
            $applied++;
        }

        return [
            'total' => $questions->count(),
            'applied' => $applied,
            'errors' => $errors
        ];
    }
}
