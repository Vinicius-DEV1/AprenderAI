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
    protected $availabilityService;

    public function __construct(QuestionAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }
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

        foreach ($distribution as $subject => $subjectTotal) {
            if ($subjectTotal <= 0)
                continue;

            \Illuminate\Support\Facades\Log::info("Processing Subject: $subject | Total Needed: $subjectTotal");

            // 1. Calculate Quotas (90% Real / 10% IA)
            $countGenTarget = (int)ceil($subjectTotal * 0.10);
            $countRealTarget = $subjectTotal - $countGenTarget;

            // 2. Use Availability Service to get pools
            $availability = $this->availabilityService->getAvailableQuestions($user, $subject, $subjectTotal);
            $realPool = $availability['real'];
            $aiPool = $availability['ai'];

            $subjectQuestions = collect();

            // 3. Populate 90% Real Quota
            $pickedReal = $realPool->take($countRealTarget);
            $subjectQuestions = $subjectQuestions->merge($pickedReal);
            $remainingRealPool = $realPool->diff($pickedReal);

            // 4. Populate 10% IA Quota (from Existing)
            $pickedAi = $aiPool->take($countGenTarget);
            $subjectQuestions = $subjectQuestions->merge($pickedAi);
            $remainingAiPool = $aiPool->diff($pickedAi);

            // 5. Fill remaining gaps (if Real or existing IA was not enough for their targets)
            $missingBeforeGen = $subjectTotal - $subjectQuestions->count();
            if ($missingBeforeGen > 0) {
                // Try to fill with remaining Real first
                $extraReal = $remainingRealPool->take($missingBeforeGen);
                $subjectQuestions = $subjectQuestions->merge($extraReal);
                $missingBeforeGen = $subjectTotal - $subjectQuestions->count();

                // Then try to fill with remaining IA existing
                if ($missingBeforeGen > 0) {
                    $extraAi = $remainingAiPool->take($missingBeforeGen);
                    $subjectQuestions = $subjectQuestions->merge($extraAi);
                }
            }

            // 6. AI Generation Fallback (ONLY if strictly missing to reach total OR 10% quota)
            $currentAiCount = $subjectQuestions->where('source', 'ai_generated')->count();
            $missingForQuota = $countGenTarget - $currentAiCount;
            $missingForTotal = $subjectTotal - $subjectQuestions->count();

            $toGenerate = max($missingForQuota, $missingForTotal);

            if ($toGenerate > 0) {
                try {
                    $aiService = app(\App\Services\AIService::class);
                    $batchSize = 5;
                    $attempts = 0;
                    $maxAttempts = 3;

                    while ($toGenerate > 0 && $attempts < $maxAttempts) {
                        $attempts++;
                        $chunkCount = min($batchSize, $toGenerate);
                        $newQuestions = $aiService->generateQuestions($subject, $chunkCount);

                        if (empty($newQuestions))
                            break;

                        foreach ($newQuestions as $nq) {
                            if (!empty($nq['statement']) && !empty($nq['alternatives'])) {
                                $createdQ = Question::create([
                                    'type' => $type,
                                    'subject' => $subject,
                                    'theme' => null,
                                    'difficulty' => $nq['difficulty'] ?? 'medium',
                                    'year' => $nq['year'] ?? rand(2015, 2025),
                                    'statement' => $nq['statement'],
                                    'alternatives' => $nq['alternatives'],
                                    'correct_answer' => $nq['correct_answer'] ?? 'A',
                                    'explanation' => $nq['explanation'] ?? null,
                                    'source' => 'ai_generated',
                                    'origin' => 'IA'
                                ]);

                                $subjectQuestions->push($createdQ);
                                $toGenerate--;
                                if ($toGenerate <= 0)
                                    break;
                            }
                        }
                    }
                }
                catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("AI Generation failed for subject $subject: " . $e->getMessage());
                }
            }

            // 7. Desperate Fallback: If still missing, ignore the "last 10" and take Real
            if ($subjectQuestions->count() < $subjectTotal) {
                $stillNeeded = $subjectTotal - $subjectQuestions->count();
                $desperateReal = Question::where('subject', $subject)
                    ->where(function ($q) {
                    $q->where('source', 'enem_real_2009_2023')->orWhere('source', 'manual');
                })
                    ->whereNotIn('id', $subjectQuestions->pluck('id'))
                    ->inRandomOrder()
                    ->limit($stillNeeded)
                    ->get();

                $subjectQuestions = $subjectQuestions->merge($desperateReal);
            }

            $finalQuestions = $finalQuestions->merge($subjectQuestions->take($subjectTotal));
        }

        return $finalQuestions;
    }
}
