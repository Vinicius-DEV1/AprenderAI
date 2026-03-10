<?php

namespace App\Services\AI;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\QuestionTriageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIBatchService
{
    protected $aiService;
    protected $promptService;
    protected $triageService;

    public function __construct(
        AIService $aiService,
        \App\Services\PromptService $promptService,
        QuestionTriageService $triageService
    ) {
        $this->aiService = $aiService;
        $this->promptService = $promptService;
        $this->triageService = $triageService;
    }

    public function processBatch(Collection $questions, string $type, ?string $model = null, ?string $batchId = null, bool $reprocess = false, ?int $userId = null): array
    {
        $prompt = $this->buildBatchPrompt($questions, $type, $reprocess);
        try {
            // Use generateJsonForBatch to route through CAPABILITY_TRIAGE keys with full failover support
            $result = $this->aiService->generateJsonForBatch($prompt, $batchId, $userId);
            $data = $result['data'] ?? [];
            $usage = $result['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0];
            $cost = $result['estimated_cost'] ?? 0;
            $appliedData = $this->applyResults($questions, $data, $type, $reprocess, $batchId);
            $appliedData['usage'] = $usage;
            $appliedData['estimated_cost'] = $cost;

            \Illuminate\Support\Facades\DB::flushQueryLog();
            if (gc_enabled())
                gc_collect_cycles();
            return $appliedData;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $clearMessage = "Falha no processamento do lote (Qtd: " . $questions->count() . "). Motivo: ";

            if (str_contains($msg, 'Syntax error') || str_contains($msg, 'JSON')) {
                $clearMessage .= "A IA retornou um texto que não é um JSON válido. O texto pode ter sido gerado pela metade devido ao limite máximo de saída de tokens (Model Output Limit excedido) para esse modelo.";
            } elseif (str_contains($msg, '429') || str_contains($msg, 'Quota Exceeded')) {
                $clearMessage .= "Limite de uso da API atingido (Rate Limit ou Quota Exceeded). Aguarde alguns minutos e tente processar um lote menor.";
            } elseif (str_contains($msg, '503') || str_contains($msg, 'Overloaded')) {
                $clearMessage .= "O servidor da IA está sobrecarregado no momento (503 Service Unavailable).";
            } else {
                $clearMessage .= $msg;
            }

            Log::error("[AIBATCH] " . $clearMessage, [
                'batch_id' => $batchId,
                'type' => $type,
                'count' => $questions->count(),
                'original_exception' => $msg
            ]);
            throw new \Exception($clearMessage, 0, $e);
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

        $subjectsRef = Subject::withCount('questions')
            ->orderBy('questions_count', 'desc')
            ->take(25)
            ->pluck('name', 'id')
            ->toArray();

        $topicsRef = Topic::withCount('questions')
            ->orderBy('questions_count', 'desc')
            ->take(25)
            ->pluck('name', 'id')
            ->toArray();

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
        $prompt .= "3. CLASSIFICAÇÃO (MÚLTIPLOS ASSUNTOS):\n";
        $prompt .= "   - Se a questão for genuinamente interdisciplinar, retorne um ARRAY de assuntos/matérias. SE NÃO, retorne apenas 1 item no array.\n";
        $prompt .= "   - Nunca combine dois assuntos em uma mesma string; se houver mais de um tema, use obrigatoriamente itens separados no array.\n";
        $prompt .= "   - Se não usar ID existente, retorne string nova no array. `subjects` = áreas (ex: 'Matemática'). `topics` = assuntos (ex: 'Trigonometria'). MÁXIMO 1 a 4 palavras. Iniciais Maiúsculas.\n";
        $prompt .= "4. TRIAGEM ESTRUTURAL OBRIGATÓRIA: Para CADA questão, preencha o campo `triage` com:\n";
        $prompt .= "   - `issues`: array de problemas detectados. Valores possíveis:\n";
        $prompt .= "     * `no_alternatives` — questão objetiva sem alternativas\n";
        $prompt .= "     * `no_statement` — tem alternativas mas enunciado vazio ou ausente\n";
        $prompt .= "     * `wrong_answer` — gabarito inválido: nenhum correto, múltiplos corretos, ou gabarito inexistente nas alternativas\n";
        $prompt .= "     * `has_image` — questão contém imagem (requer revisão visual humana)\n";
        $prompt .= "     * `missing_image` — enunciado referencia imagem (ex: 'observe a figura', 'analise o gráfico', 'conforme a imagem', 'veja o quadro') mas nenhuma imagem foi fornecida\n";
        $prompt .= "     * `missing_support_text` — contexto indica que deveria haver texto-base (questão de interpretação, filosofia, sociologia, etc.) mas não há\n";
        $prompt .= "   - `quality_score`: inteiro 0-100 representando qualidade pedagógica.\n";
        $prompt .= "     Fatores que reduzem o score: alternativas absurdas, enunciado ambíguo, distratores fracos, inconsistência de dificuldade, gabarito duvidoso.\n";
        $prompt .= "     Guia: 90-100=excelente, 75-89=bom, 60-74=aceitável, abaixo de 60=requer revisão manual.\n";
        $prompt .= "     Se a questão não tiver problemas estruturais óbvios, retorne `issues: []` e `quality_score` proporcional à qualidade pedagógica.\n\n";
        $prompt .= "DADOS DAS QUESTOES:\n" . json_encode($questionsData) . "\n\n";
        $prompt .= "REFERENCIAS (Use IDs se houver correspondencia):\n";
        $prompt .= "Disciplinas: " . json_encode($subjectsRef) . "\n";
        $prompt .= "Assuntos: " . json_encode($topicsRef) . "\n\n";
        $prompt .= "RESPOSTA: Retorne APENAS um Array JSON puro. NÃO use blocos de código markdown (```json). MANTENHA RIGOROSAMENTE OS IDs ORIGINAIS DAS QUESTÕES fornecidos no JSON de entrada.\n";
        $prompt .= 'Formato: [{"id": ID_ORIGINAL, "difficulty": "easy|medium|hard|null", "difficulty_reasoning": "...", "explanation": "...", "subjects": [ID|"string"], "topics": [ID|"string"], "suggested_answer": "...|null", "triage": {"issues": [], "quality_score": 85}}]';
        return $prompt;
    }

    protected function applyResults(Collection $questions, array $results, string $type, bool $reprocess, ?string $batchId = null): array
    {
        $applied = 0;
        $errors = [];
        $stats = [
            'difficulty' => 0,
            'explanation' => 0,
            'subjects' => 0,
            'topics' => 0,
            'sent_to_review' => 0,
            'approved' => 0,
            'low_quality' => 0,
        ];

        // Log para depuração de erros massivos (ajuda a ver o que a IA mandou)
        \Illuminate\Support\Facades\Log::debug("[AIBATCH] Raw result keys detected: " . implode(', ', array_keys($results)));
        if (count($results) === 0) {
            \Illuminate\Support\Facades\Log::warning("[AIBATCH] Advertência: A IA retornou um array vazio ou inválido.");
        }
        if (is_array($results)) {
            // Se a IA devolver um wrapper com a chave text contendo string JSON (Gemini via Guzzle sem parse completo)
            if (isset($results['text']) && is_string($results['text'])) {
                $sanitizer = app(\App\Services\AI\ResponseSanitizer::class);
                $sanitized = $sanitizer->sanitize($results['text']);
                if (is_array($sanitized)) {
                    $results = $sanitized;
                }
            }

            if (isset($results['data']) && is_array($results['data']) && !isset($results[0])) {
                $results = $results['data'];
            } elseif (isset($results['questions']) && is_array($results['questions']) && !isset($results[0])) {
                $results = $results['questions'];
            } elseif (isset($results['results']) && is_array($results['results']) && !isset($results[0])) {
                $results = $results['results'];
            } elseif (count($results) === 1 && is_array(reset($results))) {
                $first = reset($results);
                if (isset($first[0]) && is_array($first[0])) {
                    $results = $first;
                }
            }

            // Handles associative arrays like: {"123": {"difficulty": "easy"}}
            $firstElem = reset($results);
            if (is_array($firstElem) && !isset($firstElem['id'])) {
                $normalized = [];
                foreach ($results as $k => $v) {
                    if (is_array($v)) {
                        $v['id'] = $k;
                        $normalized[] = $v;
                    }
                }
                if (count($normalized) > 0) {
                    $results = $normalized;
                }
            }
        }
        $resultsCollection = collect($results);

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

            $data = $resultsCollection->firstWhere('id', (int) $question->id)
                ?? $resultsCollection->firstWhere('id', (string) $question->id);

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
                if (!empty($explanation)) {
                    $stats['explanation']++;
                }

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

            if (!empty($difficulty) && $difficulty !== $question->difficulty) {
                $stats['difficulty']++;
            }
            if (!empty($explanation) && $explanation !== $question->explanation) {
                $stats['explanation']++;
            }

            $question->update([
                'difficulty' => $difficulty,
                'difficulty_reasoning' => $reasoning,
                'explanation' => $explanation,
                // A review_status será definida após a classificação para garantir integridade
            ]);

            // Resolucao de Disciplina (Subjects)
            $subjectsVal = $data['subjects'] ?? ($data['subject'] ?? ($data['subject_id'] ?? []));
            $subjectsArray = is_array($subjectsVal) ? $subjectsVal : [$subjectsVal];
            $subjectIdsToSync = [];

            foreach ($subjectsArray as $subjectItem) {
                if (empty($subjectItem))
                    continue;

                $subjectId = is_numeric($subjectItem) ? $subjectItem : null;
                $subjectName = !is_numeric($subjectItem) ? $subjectItem : null;

                if (!$subjectId && !empty($subjectName)) {
                    $subjectName = (string) $subjectName;
                    $normalizedName = strtolower(trim($subjectName));

                    $existingSubject = Subject::whereRaw('LOWER(name) = ?', [$normalizedName])
                        ->orWhere('name', 'like', '%' . trim($subjectName) . '%')
                        ->first();

                    if ($existingSubject) {
                        $subjectId = $existingSubject->id;
                    } else {
                        $subject = Subject::firstOrCreate(
                            ['name' => mb_convert_case(trim($subjectName), MB_CASE_TITLE, "UTF-8")],
                            ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'concurso']
                        );
                        $subjectId = $subject->id;
                    }
                }

                if ($subjectId) {
                    if (!Subject::where('id', $subjectId)->exists()) {
                        Log::warning("[AIBATCH] AI alucinou ID de Disciplina: {$subjectId} para Questão #{$question->id}. Ignorando ID e tratando como erro parcial.");
                        $subjectId = null;
                    }
                }

                if ($subjectId) {
                    $subjectIdsToSync[] = $subjectId;
                }
            }

            if (!empty($subjectIdsToSync)) {
                if ($reprocess || $question->subjects->isEmpty()) {
                    $question->subjects()->sync($subjectIdsToSync);
                    $stats['subjects']++;
                }
            }

            // Resolucao de Assunto (Topics)
            $topicsVal = $data['topics'] ?? ($data['topic'] ?? ($data['topic_id'] ?? []));
            $topicsArray = is_array($topicsVal) ? $topicsVal : [$topicsVal];
            $topicIdsToSync = [];

            foreach ($topicsArray as $topicItem) {
                if (empty($topicItem))
                    continue;

                $topicId = is_numeric($topicItem) ? $topicItem : null;
                $topicName = !is_numeric($topicItem) ? $topicItem : null;

                if (!$topicId && !empty($topicName)) {
                    $topicName = (string) $topicName;
                    $normalizedTopicName = strtolower(trim($topicName));

                    $existingTopic = Topic::whereRaw('LOWER(name) = ?', [$normalizedTopicName])
                        ->orWhere('name', 'like', '%' . trim($topicName) . '%')
                        ->first();

                    if ($existingTopic) {
                        $topicId = $existingTopic->id;
                    } else {
                        $topic = Topic::firstOrCreate(
                            ['name' => mb_convert_case(trim($topicName), MB_CASE_TITLE, "UTF-8")],
                            ['slug' => \Illuminate\Support\Str::slug($topicName)]
                        );
                        $topicId = $topic->id;
                    }
                }

                if ($topicId) {
                    if (!Topic::where('id', $topicId)->exists()) {
                        Log::warning("[AIBATCH] AI alucinou ID de Assunto: {$topicId} para Questão #{$question->id}. Ignorando ID e tratando como erro parcial.");
                        $topicId = null;
                    }
                }

                if ($topicId) {
                    $topicIdsToSync[] = $topicId;
                }
            }

            if (!empty($topicIdsToSync)) {
                if ($reprocess || $question->topics->isEmpty()) {
                    $question->topics()->sync($topicIdsToSync);
                    $stats['topics']++;
                }
            }

            // --- Triagem Estrutural (dados retornados pela IA) ---
            $triageData = is_array($data['triage'] ?? null) ? $data['triage'] : [];
            $triageIssues = is_array($triageData['issues'] ?? null) ? $triageData['issues'] : [];
            $qualityScore = isset($triageData['quality_score']) ? (int) $triageData['quality_score'] : 100;

            // --- Validação Final de Status ---
            // Recarregamos o modelo e relações para garantir que o estado em memória reflita o DB (após os syncs)
            $question->refresh();
            $question->load('subjects', 'topics', 'alternatives');

            $subCount = $question->subjects->count();
            $topCount = $question->topics->count();
            $hasClassification = ($subCount > 0 && $topCount > 0);

            // Decisão de status:
            // 1. IA detectou issues estruturais → revisão manual
            // 2. qualityScore < 60 → revisão manual
            // 3. Gabarito divergiu (suggested_answer diferente) → revisão manual
            // 4. Classificação incompleta (apenas quando type exige) → revisão manual
            // 5. Caso contrário → aprovado
            $finalStatus = 'approved';
            if (!empty($triageIssues) || $qualityScore < 60) {
                $finalStatus = 'review';
            } elseif ($needsManualReview) {
                $finalStatus = 'review';
            } elseif (in_array($type, ['both', 'classification']) && !$hasClassification) {
                $finalStatus = 'review';
            }

            $question->update(['review_status' => $finalStatus]);

            // Registrar log de triagem automática
            $changesSnapshot = [
                'before' => $snapshotBefore,
                'after' => [
                    'difficulty' => $question->difficulty,
                    'difficulty_reasoning' => $question->difficulty_reasoning,
                    'explanation' => $question->explanation,
                    'subjects' => $question->subjects->pluck('id')->toArray(),
                    'topics' => $question->topics->pluck('id')->toArray(),
                    'quality_score' => $qualityScore,
                    'issues' => $triageIssues,
                ],
            ];
            $this->triageService->logAutoTriage($question, $triageIssues, $qualityScore, $changesSnapshot);

            // Atualizar contadores de stats
            if ($finalStatus === 'review') {
                $stats['sent_to_review']++;
            } else {
                $stats['approved']++;
            }
            if ($qualityScore < 60) {
                $stats['low_quality']++;
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
                        'quality_score' => $qualityScore,
                        'issues' => $triageIssues,
                    ],
                ]);
            }
        }
        return ['total' => $questions->count(), 'applied' => $applied, 'errors' => $errors, 'stats' => $stats];
    }
}
