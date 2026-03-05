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
            $usage = $result['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0];
            $appliedData = $this->applyResults($questions, $data, $type, $reprocess, $batchId);
            $appliedData['usage'] = $usage;

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
                'type' => $q->type,
                'format' => $q->format,
                'tipo_questao' => $q->tipo_questao,
                'alternatives' => $q->alternativesAsMap(),
                'correct_label' => $q->correct_answer,
                'discursive_answer' => $q->discursive_answer,
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
        $prompt = "Você é o Xavier, Mentor de Elite da AprovadoAI e Curador Chefe do Banco de Questões.\n\n";
        $prompt .= "TAREFA: " . $instruction . "\n\n";
        $prompt .= "REGRAS DE PROCESSAMENTO OBRIGATÓRIAS:\n";
        $prompt .= "1. TIPO DE QUESTÃO (`tipo_questao` / `format`):\n";
        $prompt .= "   - MÚLTIPLA ESCOLHA: Resolva a questão. Se o `correct_label` não for a resposta correta, informe a certa em `suggested_answer`.\n";
        $prompt .= "   - CERTO/ERRADO: Valide se a afirmação está Certa ou Errada.\n";
        $prompt .= "   - DISCURSIVA: Crie uma resposta pedagógica, pois não há alternativas.\n";
        $prompt .= "   - REDAÇÃO: Para temas de redação, gere APENAS Feedback Pedagógico em `explanation` e deixe o resto null.\n";
        $prompt .= "2. LATEX OBRIGATÓRIO: Use \\( ... \\) e \\[ ... \\] para qualquer fórmula matemática/física.\n\n";
        $prompt .= "DADOS DAS QUESTOES:\n" . json_encode($questionsData) . "\n\n";
        $prompt .= "REFERENCIAS (Use IDs se houver correspondencia):\n";
        $prompt .= "Disciplinas: " . json_encode($subjectsRef) . "\n";
        $prompt .= "Assuntos: " . json_encode($topicsRef) . "\n\n";
        $prompt .= "RESPOSTA: Retorne APENAS um Array JSON puro: [{\"id\": 1, \"difficulty\": \"easy|medium|hard|null\", \"difficulty_reasoning\": \"...\", \"explanation\": \"...\", \"subject\": ID|null, \"topic\": ID|null, \"suggested_answer\": \"...|null\"}]";
        return $prompt;
    }

    protected function applyResults(Collection $questions, array $results, string $type, bool $reprocess, ?string $batchId = null): array
    {
        $applied = 0;
        $errors = [];

        foreach ($questions as $question) {
            $snapshotBefore = [
                'difficulty' => $question->difficulty,
                'difficulty_reasoning' => $question->difficulty_reasoning,
                'explanation' => $question->explanation,
                'subjects' => $question->subjects->pluck('id')->toArray(),
                'topics' => $question->topics->pluck('id')->toArray(),
            ];

            $batchItem = null;
            if ($batchId) {
                $batchItem = \App\Models\AiBatchItem::create([
                    'batch_id' => $batchId,
                    'question_id' => $question->id,
                    'status' => 'pending',
                    'snapshot_before' => $snapshotBefore,
                ]);
            }

            $data = collect($results)->firstWhere('id', $question->id);
            if (!is_array($data)) {
                if ($batchItem) {
                    $batchItem->update([
                        'status' => 'failed',
                        'error_message' => 'Nenhum dado retornado pela IA para esta questão.',
                    ]);
                }
                $errors[] = "Questão #{$question->id}: Falha ao processar dados.";
                continue;
            }

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

            // Lógica de Redação: Ignorar dificuldade e classificação
            $isEssay = strtolower($question->format ?? '') === 'redacao' || strtolower($question->tipo_questao ?? '') === 'redacao';
            if ($isEssay) {
                $question->update([
                    'explanation' => $explanation,
                    'review_status' => 'approved'
                ]);
                $applied++;

                if ($batchItem) {
                    $batchItem->update([
                        'status' => 'processed',
                        'snapshot_after' => [
                            'difficulty' => $question->difficulty,
                            'difficulty_reasoning' => $question->difficulty_reasoning,
                            'explanation' => $question->explanation,
                            'subjects' => $question->subjects->pluck('id')->toArray(),
                            'topics' => $question->topics->pluck('id')->toArray(),
                        ],
                    ]);
                }

                continue; // Pula classificação de Subjects/Topics abaixo
            }

            // Lógica de Gabarito Divergente para Múltipla Escolha / Certo-Errado
            $suggestedAnswer = $data['suggested_answer'] ?? null;
            $needsManualReview = false;
            if (!empty($suggestedAnswer)) {
                $isCorrectLabel = strtolower(trim($suggestedAnswer)) === strtolower(trim($question->correct_answer ?? ''));
                if (!$isCorrectLabel) {
                    $needsManualReview = true;
                }
            }

            $question->update([
                'difficulty' => $difficulty,
                'difficulty_reasoning' => $reasoning,
                'explanation' => $explanation,
                'review_status' => $needsManualReview ? 'pending' : 'approved'
            ]);

            // Resolucao de Disciplina (Subject)
            $subjectVal = $data['subject'] ?? ($data['subject_id'] ?? null);
            $subjectId = is_numeric($subjectVal) ? $subjectVal : null;
            $subjectName = !is_numeric($subjectVal) ? $subjectVal : ($data['subject_name'] ?? null);

            if (!$subjectId && !empty($subjectName)) {
                $normalizedName = strtolower(trim($subjectName));

                // Força mapeamento de variantes comuns para o padrão oficial do BD
                if (in_array($normalizedName, ['português', 'portugues', 'língua portuguesa', 'lingua portuguesa'])) {
                    $subjectName = 'Língua Portuguesa';
                    $normalizedName = 'língua portuguesa';
                }

                // Tenta achar pelo nome exato ou parecido ignorando case antes de criar um novo
                $existingSubject = Subject::whereRaw('LOWER(name) = ?', [$normalizedName])
                    ->orWhere('name', 'like', '%' . trim($subjectName) . '%')
                    ->first();

                if ($existingSubject) {
                    $subjectId = $existingSubject->id;
                } else {
                    $subject = Subject::firstOrCreate(
                        ['name' => trim($subjectName)],
                        ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'concurso']
                    );
                    $subjectId = $subject->id;
                }
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
                $normalizedTopicName = strtolower(trim($topicName));

                // Tenta achar pelo nome exato ou parecido ignorando case antes de criar um novo
                $existingTopic = Topic::whereRaw('LOWER(name) = ?', [$normalizedTopicName])
                    ->orWhere('name', 'like', '%' . trim($topicName) . '%')
                    ->first();

                if ($existingTopic) {
                    $topicId = $existingTopic->id;
                } else {
                    $topic = Topic::firstOrCreate(
                        ['name' => trim($topicName)],
                        ['slug' => \Illuminate\Support\Str::slug($topicName)]
                    );
                    $topicId = $topic->id;
                }
            }
            if ($topicId) {
                if ($reprocess || $question->topics->isEmpty()) {
                    $question->topics()->sync([$topicId]);
                }
            }

            $applied++;

            if ($batchItem) {
                // Reload relationships to ensure snapshot_after gets fresh data
                $question->load('subjects', 'topics');
                $batchItem->update([
                    'status' => 'processed',
                    'snapshot_after' => [
                        'difficulty' => $question->difficulty,
                        'difficulty_reasoning' => $question->difficulty_reasoning,
                        'explanation' => $question->explanation,
                        'subjects' => $question->subjects->pluck('id')->toArray(),
                        'topics' => $question->topics->pluck('id')->toArray(),
                    ],
                ]);
            }
        }
        return ['total' => $questions->count(), 'applied' => $applied, 'errors' => $errors];
    }
}
