<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Simulation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SimulationCreationService
{
    /**
     * Step 1: Create the simulation record with 'generating' status.
     * This is synchronous and fast.
     */
    public function createPendingSimulation(User $user, array $data): Simulation
    {
        set_time_limit(300);
        $total = (int)$data['total_questions'];
        $distribution = $data['subject_distribution'] ?? [];
        $type = $data['type'];

        return Simulation::create([
            'user_id' => $user->id,
            'type' => $type,
            'configuration' => [
                'questions' => $total,
                'subject_distribution' => $distribution,
                'include_essay' => $data['include_essay'] ?? false,
                'time_limit' => $type === 'enem'
                ? (($data['include_essay'] ?? false) ? 19800 : 16200)
                : (int)($data['custom_time'] ?? 10800),
            ],
            'status' => 'generating', // Initial status for async flow
        ]);
    }

    /**
     * Step 2: Process questions (Select DB + Generate AI).
     * This runs inside the Job.
     */
    public function processSimulationQuestions(Simulation $simulation, array $data): void
    {
        // 1. Select Questions (Outside Transaction - Heavy processing & AI calls)
        $total = (int)$data['total_questions'];
        $distribution = $data['subject_distribution'] ?? [];
        $type = $data['type'];

        // This might take 30-60s if AI is needed.
        // We do NOT want to hold a DB transaction (especially on SQLite) during this time.
        $questions = $this->selectQuestions($simulation->user, $type, $total, $distribution);

        // Validation
        if ($questions->count() !== $total) {
            if ($questions->isEmpty()) {
                throw new \Exception("Nenhuma questão encontrada para os critérios.");
            }
        }

        // 2. Persistence (Inside Transaction - Fast)
        DB::transaction(function () use ($simulation, $questions) {
            $now = now();
            $rows = $questions->map(fn($q) => [
            'simulation_id' => $simulation->id,
            'question_id' => $q->id,
            'user_answer' => null,
            'is_correct' => false,
            'time_spent' => 0,
            'marked_for_review' => false,
            'created_at' => $now,
            'updated_at' => $now,
            ])->all();

            DB::table('simulation_answers')->insert($rows);

            $simulation->user->incrementSimulationUsage();
        });
    }

    /**
     * Wrapper for backward compatibility or direct sync calls if needed.
     */
    public function createSimulation(User $user, array $data): Simulation
    {
        $simulation = $this->createPendingSimulation($user, $data);
        $this->processSimulationQuestions($simulation, $data);
        // Sync update to pending
        $simulation->update(['status' => 'pending']);
        $simulation->startSimulation();
        return $simulation;
    }

    /**
     * Get IDs of questions seen in the last 10 finished simulations.
     */
    protected function getLastSeenQuestionIds(User $user, int $limit = 10): array
    {
        $lastSimulations = Simulation::where('user_id', $user->id)
            ->where('status', 'finished')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');

        if ($lastSimulations->isEmpty()) {
            return [];
        }

        return DB::table('simulation_answers')
            ->whereIn('simulation_id', $lastSimulations)
            ->pluck('question_id')
            ->toArray();
    }

    /**
     * Select questions respecting business rules.
     */
    protected function selectQuestions(User $user, string $type, int $total, array $distribution): Collection
    {
        $finalQuestions = collect();
        $usedStatements = []; // Global deduplication by statement hash

        // 1. Get Ignored IDs (Recurrence Filter)
        $ignoredIds = $this->getLastSeenQuestionIds($user);

        // 2. Define Subject Order
        if ($type === 'enem') {
            $orderedSubjects = ['português', 'matemática'];
            foreach (array_keys($distribution) as $s) {
                if (!in_array($s, $orderedSubjects)) {
                    $orderedSubjects[] = $s;
                }
            }
        }
        else {
            $orderedSubjects = array_keys($distribution);
        }

        foreach ($orderedSubjects as $subject) {
            $subjectTotal = (int)($distribution[$subject] ?? 0);
            \Illuminate\Support\Facades\Log::info("Processing Subject: $subject | Total Needed: $subjectTotal");

            if ($subjectTotal <= 0) {
                continue;
            }

            if ($type === 'enem' && in_array($subject, ['português', 'matemática'])) {
                \Illuminate\Support\Facades\Log::info("DEBUG TRACE: Entrou no IF de geração para $subject. CountGen calculado...");
                // RULE: 90% Real + 10% Generated
                // Calculate quotas
                $countGen = (int)ceil($subjectTotal * 0.10); // At least 1 if total > 0
                $countReal = $subjectTotal - $countGen;

                // 1. Fetch Real Questions (Apply Filter + Recurrence)
                // NOTE: Real questions are marked as 'concurso' type in DB but have official source.
                $realCandidates = Question::where('subject', $subject)
                    ->where(function ($q) {
                    $q->where('source', 'enem_real_2009_2023')
                        ->orWhere('source', 'manual');
                })
                    ->whereNotIn('id', $ignoredIds)
                    ->inRandomOrder()
                    ->limit($countReal * 2)
                    ->get();

                // Fallback for real questions
                if ($realCandidates->count() < $countReal) {
                    $fallbackCandidates = Question::where('subject', $subject)
                        ->where(function ($q) {
                        $q->where('source', 'enem_real_2009_2023')
                            ->orWhere('source', 'manual');
                    })
                        ->whereNotIn('id', $realCandidates->pluck('id'))
                        ->inRandomOrder()
                        ->limit(($countReal - $realCandidates->count()) * 2)
                        ->get();
                    $realCandidates = $realCandidates->merge($fallbackCandidates);
                }

                $subjectQuestions = collect();
                foreach ($realCandidates as $q) {
                    if ($subjectQuestions->count() >= $countReal)
                        break;

                    $stmtHash = md5(trim($q->statement));
                    if (!in_array($stmtHash, $usedStatements)) {
                        $subjectQuestions->push($q);
                        $usedStatements[] = $stmtHash;
                    }
                }

                // 2. Fetch Generated Questions (Apply Filter + Recurrence)
                $genCandidates = Question::where('subject', $subject)
                    ->where(function ($q) {
                    $q->where('source', 'generated_system')
                        ->orWhere('source', 'ai_generated');
                })
                    ->whereNotIn('id', $ignoredIds)
                    ->inRandomOrder()
                    ->limit($countGen * 3)
                    ->get();

                $pickedGen = collect();
                foreach ($genCandidates as $q) {
                    if ($pickedGen->count() >= $countGen)
                        break;

                    $stmtHash = md5(trim($q->statement));
                    if (!in_array($stmtHash, $usedStatements)) {
                        $pickedGen->push($q);
                        $usedStatements[] = $stmtHash;
                    }
                }

                // CHECK: Do we have enough generated questions?
                $missingGen = $countGen - $pickedGen->count();
                \Illuminate\Support\Facades\Log::info("DEBUG TRACE: Subject $subject | CountGen: $countGen | PickedDB: {$pickedGen->count()} | Missing: $missingGen");

                if ($missingGen > 0) {
                    // CALL AI TO GENERATE MISSING
                    try {
                        $aiService = app(\App\Services\AIService::class);
                        $batchSize = 5;
                        $attempts = 0;
                        $maxAttempts = 10;
                        $consecutiveFailures = 0;

                        while ($pickedGen->count() < $countGen && $attempts < $maxAttempts) {
                            $attempts++;
                            $stillNeeded = $countGen - $pickedGen->count();
                            $chunkCount = min($batchSize, $stillNeeded);

                            $newQuestions = $aiService->generateQuestions($subject, $chunkCount);

                            if (empty($newQuestions)) {
                                $consecutiveFailures++;
                            }
                            else {
                                $consecutiveFailures = 0;
                                foreach ($newQuestions as $nq) {
                                    if (!empty($nq['statement']) && !empty($nq['alternatives'])) {
                                        $createdQ = Question::create([
                                            'type' => 'enem',
                                            'subject' => $subject,
                                            'theme' => null,
                                            'difficulty' => 'medium',
                                            'year' => rand(2015, 2025),
                                            'statement' => $nq['statement'],
                                            'alternatives' => $nq['alternatives'],
                                            'correct_answer' => $nq['correct_answer'] ?? 'A',
                                            'explanation' => $nq['explanation'] ?? null,
                                            'source' => 'ai_generated', // FIX: Standardize as ai_generated for UI badge
                                            'origin' => 'IA'
                                        ]);

                                        $pickedGen->push($createdQ);
                                        $usedStatements[] = md5(trim($createdQ->statement));

                                        if ($pickedGen->count() >= $countGen)
                                            break;
                                    }
                                }
                            }

                            if ($consecutiveFailures >= 2)
                                break;
                        }
                    }
                    catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("AI Generation failed for subject $subject: " . $e->getMessage());
                    }
                }

                // If still missing (IA failed), fill with Real (recurrence filtered first)
                $stillMissing = $countGen - $pickedGen->count();
                if ($stillMissing > 0) {
                    $extraReal = Question::where('subject', $subject)
                        ->where(function ($q) {
                        $q->where('source', 'enem_real_2009_2023')
                            ->orWhere('source', 'manual');
                    })
                        ->whereNotIn('id', array_merge($subjectQuestions->pluck('id')->toArray(), $ignoredIds))
                        ->inRandomOrder()
                        ->limit($stillMissing)
                        ->get();

                    if ($extraReal->count() < $stillMissing) {
                        $extraRealFallback = Question::where('subject', $subject)
                            ->where(function ($q) {
                            $q->where('source', 'enem_real_2009_2023')
                                ->orWhere('source', 'manual');
                        })
                            ->whereNotIn('id', array_merge($subjectQuestions->pluck('id')->toArray(), $extraReal->pluck('id')->toArray()))
                            ->inRandomOrder()
                            ->limit($stillMissing - $extraReal->count())
                            ->get();
                        $extraReal = $extraReal->merge($extraRealFallback);
                    }

                    $pickedGen = $pickedGen->merge($extraReal);
                }

                $subjectQuestions = $subjectQuestions->merge($pickedGen);
                $finalQuestions = $finalQuestions->merge($subjectQuestions);

            }
            else {
                \Illuminate\Support\Facades\Log::info("DEBUG TRACE: Caiu no ELSE para $subject (Type: $type)");
                // Selection for other types
                $candidates = Question::where('type', $type)
                    ->where('subject', $subject)
                    ->whereNotIn('id', $ignoredIds)
                    ->inRandomOrder()
                    ->limit($subjectTotal)
                    ->get();

                if ($candidates->count() < $subjectTotal) {
                    $fallbackCandidates = Question::where('type', $type)
                        ->where('subject', $subject)
                        ->whereNotIn('id', $candidates->pluck('id'))
                        ->inRandomOrder()
                        ->limit($subjectTotal - $candidates->count())
                        ->get();
                    $candidates = $candidates->merge($fallbackCandidates);
                }

                $finalQuestions = $finalQuestions->merge($candidates);
            }
        }

        return $finalQuestions;
    }
}
