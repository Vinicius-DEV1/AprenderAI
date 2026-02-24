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

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function processBatch(Collection $questions, string $type, ?string $model = null): array
    {
        $prompt = $this->buildBatchPrompt($questions, $type);
        try {
            $result = $this->aiService->generateJson($prompt, $model);
            $data = $result['data'] ?? [];
            $appliedData = $this->applyResults($questions, $data, $type);
            \Illuminate\Support\Facades\DB::flushQueryLog();
            if (gc_enabled()) gc_collect_cycles();
            return $appliedData;
        } catch (\Exception $e) {
            Log::error("AIBatchService: Falha no processamento do lote: " . $e->getMessage());
            throw $e;
        }
    }

    protected function buildBatchPrompt(Collection $questions, string $type): string
    {
        $questionsData = $questions->map(fn($q) => [
            'id' => $q->id,
            'statement' => $q->statement,
            'alternatives' => $q->alternativesAsMap(),
            'correct_label' => $q->correct_answer,
            'missing_fields' => []
        ]);

        $subjectsRef = json_encode(Subject::pluck('name', 'id')->toArray());
        $topicsRef = json_encode(Topic::pluck('name', 'id')->toArray());

        $instruction = match ($type) {
            'both' => "Avalie a dificuldade com um Raciocinio curto, gere uma explicacao pedagogica, e classifique a Disciplina e o Assunto.",
            default => "Avalie a questao e forneca os dados necessarios."
        };

        $json = json_encode($questionsData);

        $prompt = "Atue como um Especialista em Educacao e IA.\n\n";
        $prompt .= "TAREFA: " . $instruction . "\n\n";
        $prompt .= "DADOS DAS QUESTOES:\n" . $json . "\n\n";
        $prompt .= "REFERENCIAS DE CLASSIFICACAO (Use SOMENTE os IDs abaixo se houver correspondencia):\n";
        $prompt .= "Disciplinas (Subjects): " . $subjectsRef . "\n";
        $prompt .= "Assuntos (Topics): " . $topicsRef . "\n\n";
        $prompt .= "REGRAS DE CLASSIFICACAO:\n";
        $prompt .= "1. Se a Disciplina ou Assunto NAO existir nas referencias acima, sugira um NOME claro e conciso nos campos 'subject_name' e 'topic_name'.\n";
        $prompt .= "2. Use preferencialmente os IDs existentes ('subject_id', 'topic_id').\n";
        $prompt .= "3. Responda APENAS um Array JSON: [{\"id\": 1, \"difficulty\": \"easy\", \"reasoning\": \"...\", \"explanation\": \"...\", \"subject_id\": ID_OU_NULL, \"subject_name\": \"NOME_NOVO_OU_NULL\", \"topic_id\": ID_OU_NULL, \"topic_name\": \"NOME_NOVO_OU_NULL\"}]";

        return $prompt;
    }

    protected function applyResults(Collection $questions, array $results, string $type): array
    {
        $applied = 0;
        foreach ($questions as $question) {
            $data = collect($results)->firstWhere('id', $question->id);
            if (!is_array($data)) continue;

            $question->update([
                'difficulty' => $data['difficulty'] ?? $question->difficulty,
                'difficulty_reasoning' => $data['reasoning'] ?? $question->difficulty_reasoning,
                'explanation' => $data['explanation'] ?? $question->explanation,
                'review_status' => 'approved'
            ]);

            // Resolucao de Disciplina (Subject)
            $subjectId = $data['subject_id'] ?? null;
            if (!$subjectId && !empty($data['subject_name'])) {
                $subject = Subject::firstOrCreate(
                    ['name' => $data['subject_name']],
                    ['slug' => Str::slug($data['subject_name']), 'type' => 'concurso']
                );
                $subjectId = $subject->id;
            }
            if ($subjectId) {
                $question->subjects()->sync([$subjectId]);
            }

            // Resolucao de Assunto (Topic)
            $topicId = $data['topic_id'] ?? null;
            if (!$topicId && !empty($data['topic_name'])) {
                $topic = Topic::firstOrCreate(
                    ['name' => $data['topic_name']],
                    ['slug' => Str::slug($data['topic_name'])]
                );
                $topicId = $topic->id;
            }
            if ($topicId) {
                $question->topics()->sync([$topicId]);
            }

            $applied++;
        }
        return ['total' => $questions->count(), 'applied' => $applied, 'errors' => []];
    }
}
