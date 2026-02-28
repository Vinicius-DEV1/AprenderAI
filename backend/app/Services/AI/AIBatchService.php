<?php

namespace App\Services\AI;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIBatchService
{
    protected $aiService;
    protected $promptService;

    public function __construct(AIService $aiService, \App\Services\PromptService $promptService)
    {
        $this->aiService = $aiService;
        $this->promptService = $promptService;
    }

    public function processBatch(Collection $questions, string $type, ?string $model = null, ?string $batchId = null, bool $reprocess = false): array
    {
        $prompt = $this->buildBatchPrompt($questions, $type, $reprocess);
        try {
            $result = $this->aiService->generateJson($prompt, $model);
            $data = $result['data'] ?? [];
            $appliedData = $this->applyResults($questions, $data, $type, $reprocess);
            \Illuminate\Support\Facades\DB::flushQueryLog();
            if (gc_enabled())
                gc_collect_cycles();
            return $appliedData;
        } catch (\Exception $e) {
            Log::error("AIBatchService: Falha no processamento do lote: " . $e->getMessage());
            throw $e;
        }
    }

    protected function buildBatchPrompt(Collection $questions, string $type, bool $reprocess): string
    {
        $questionsData = $questions->map(function ($q) use ($reprocess, $type) {
            $missing = [];
            if (!$reprocess) {
                if (empty(trim($q->difficulty_reasoning)) && in_array($type, ['both', 'difficulty']))
                    $missing[] = 'difficulty_reasoning';
                if (empty(trim($q->explanation)) && in_array($type, ['both', 'explanation']))
                    $missing[] = 'explanation';
                if ($q->subjects->isEmpty() && in_array($type, ['both', 'classification']))
                    $missing[] = 'subject';
                if ($q->topics->isEmpty() && in_array($type, ['both', 'classification']))
                    $missing[] = 'topic';
            }

            return [
                'id' => $q->id,
                'statement' => $q->statement,
                'alternatives' => $q->alternativesAsMap(),
                'correct_label' => $q->correct_answer,
                'missing_fields' => $missing,
                'reprocess_all' => $reprocess
            ];
        });

        $subjectsRef = Subject::pluck('name', 'id')->toArray();
        $topicsRef = Topic::pluck('name', 'id')->toArray();

        $instruction = match ($type) {
            'both' => "Dificuldade (com Raciocinio em 'difficulty_reasoning'), Explicação pedagógica (em 'explanation'), Disciplina e Assunto.",
            'difficulty' => "Apenas Dificuldade (nível e 'difficulty_reasoning').",
            'explanation' => "Apenas Explicação pedagógica (em 'explanation').",
            'classification' => "Apenas Disciplina e Assunto.",
            default => "Avalie a questao e forneca os dados necessarios."
        };

        // Usa o SystemPrompt se existir, senão usa o fallback hardcoded melhorado
        return $this->promptService->get('triage_batch_classification', [
            'instruction' => $instruction,
            'subjects_reference' => json_encode($subjectsRef),
            'topics_reference' => json_encode($topicsRef),
            'questions_json' => json_encode($questionsData)
        ], $this->getFallbackPrompt($instruction, $subjectsRef, $topicsRef, $questionsData));
    }

    protected function getFallbackPrompt($instruction, $subjectsRef, $topicsRef, $questionsData): string
    {
        $prompt = "Atue como um Especialista em Educacao e IA.\n\n";
        $prompt .= "TAREFA: " . $instruction . "\n\n";
        $prompt .= "DADOS DAS QUESTOES:\n" . json_encode($questionsData) . "\n\n";
        $prompt .= "REFERENCIAS (Use IDs se houver correspondencia):\n";
        $prompt .= "Disciplinas: " . json_encode($subjectsRef) . "\n";
        $prompt .= "Assuntos: " . json_encode($topicsRef) . "\n\n";
        $prompt .= "RESPOSTA: Retorne APENAS um Array JSON: [{\"id\": 1, \"difficulty\": \"easy\", \"difficulty_reasoning\": \"...\", \"explanation\": \"...\", \"subject_id\": ID, \"subject_name\": \"NOME\", \"topic_id\": ID, \"topic_name\": \"NOME\"}]";
        return $prompt;
    }

    protected function applyResults(Collection $questions, array $results, string $type, bool $reprocess): array
    {
        $applied = 0;
        foreach ($questions as $question) {
            $data = collect($results)->firstWhere('id', $question->id);
            if (!is_array($data))
                continue;

            // Mapeamento defensivo para chaves variadas que a IA possa retornar
            $difficulty = $data['difficulty'] ?? $question->difficulty;
            $reasoning = $data['difficulty_reasoning'] ?? ($data['reasoning'] ?? null);
            $explanation = $data['explanation'] ?? null;

            // Preservação de dados caso $reprocess seja falso
            if (!$reprocess) {
                if (!empty(trim($question->difficulty_reasoning))) {
                    $reasoning = $question->difficulty_reasoning;
                }
                if (!empty(trim($question->explanation))) {
                    $explanation = $question->explanation;
                }
            } else {
                // Fallback para fallback antigo se reprocess for true mas a IA não devolveu
                $reasoning = $reasoning ?? $question->difficulty_reasoning;
                $explanation = $explanation ?? $question->explanation;
            }

            $question->update([
                'difficulty' => $difficulty,
                'difficulty_reasoning' => $reasoning,
                'explanation' => $explanation,
                'review_status' => 'approved'
            ]);

            // Resolucao de Disciplina (Subject)
            $subjectVal = $data['subject'] ?? ($data['subject_id'] ?? null);
            $subjectId = is_numeric($subjectVal) ? $subjectVal : null;
            $subjectName = !is_numeric($subjectVal) ? $subjectVal : ($data['subject_name'] ?? null);

            if (!$subjectId && !empty($subjectName)) {
                $subject = Subject::firstOrCreate(
                    ['name' => $subjectName],
                    ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'concurso']
                );
                $subjectId = $subject->id;
            }
            if ($subjectId) {
                if ($reprocess || $question->subjects->isEmpty()) {
                    $question->subjects()->sync([$subjectId]);
                }
            }

            // Resolucao de Assunto (Topic)
            $topicVal = $data['topic'] ?? ($data['topic_id'] ?? null);
            $topicId = is_numeric($topicVal) ? $topicVal : null;
            $topicName = !is_numeric($topicVal) ? $topicVal : ($data['topic_name'] ?? null);

            if (!$topicId && !empty($topicName)) {
                $topic = Topic::firstOrCreate(
                    ['name' => $topicName],
                    ['slug' => \Illuminate\Support\Str::slug($topicName)]
                );
                $topicId = $topic->id;
            }
            if ($topicId) {
                if ($reprocess || $question->topics->isEmpty()) {
                    $question->topics()->sync([$topicId]);
                }
            }

            $applied++;
        }
        return ['total' => $questions->count(), 'applied' => $applied, 'errors' => []];
    }
}
