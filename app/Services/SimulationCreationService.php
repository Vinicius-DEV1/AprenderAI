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
                'organization' => $data['organization'] ?? [],
                'institution' => $data['institution'] ?? [],
                'role' => $data['role'] ?? [],
            ],
            'status' => 'pending', // Initial status (mapped to generating via empty answers check)
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
        // This might take 30-60s if AI is needed.
        // We do NOT want to hold a DB transaction (especially on SQLite) during this time.
        $questions = $this->selectQuestions($simulation->user, $type, $total, $distribution, $data);

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
    protected function selectQuestions(User $user, string $type, int $total, array $distribution, array $context = []): Collection
    {
        $finalQuestions = collect();
        $lastSeenIds = [];

        if ($type === 'concurso') {
            $lastSeenIds = $this->getLastSeenQuestionIds($user, 20);
        }

        foreach ($distribution as $subject => $subjectTotal) {
            if ($subjectTotal <= 0)
                continue;

            \Illuminate\Support\Facades\Log::info("Processing Subject: $subject | Total Needed: $subjectTotal");

            if ($type === 'concurso') {
                // Concurso Logic: N:N Subject Filtering
                // We use 'whereHas' to filter questions that belong to the specific subject
                // via the 'question_subject' pivot table.
                $query = Question::where('type', 'concurso')
                    ->whereHas('subjects', function ($q) use ($subject) {
                    $q->where('name', $subject);
                });

                // Apply Filters
                if (!empty($context['organization'])) {
                    $query->whereIn('organization', $context['organization']);
                }
                if (!empty($context['institution'])) {
                    $query->whereIn('institution', $context['institution']);
                }
                if (!empty($context['role'])) {
                    $query->whereIn('role', $context['role']);
                }

                // Anti-duplication Logic:
                // We merge globally seen questions (last 20) with questions already selected
                // in the current simulation session to prevent the same question from appearing
                // multiple times (e.g., if it belongs to multiple subjects requested).
                $avoidIds = array_merge($lastSeenIds, $finalQuestions->pluck('id')->toArray());

                if (!empty($avoidIds)) {
                    $query->whereNotIn('id', $avoidIds);
                }

                $subjectQuestions = $query->inRandomOrder()->limit($subjectTotal)->get();
                $finalQuestions = $finalQuestions->merge($subjectQuestions);

                // Check if we need to generate more (AI Fallback with Context)
                $missing = $subjectTotal - $subjectQuestions->count();
                if ($missing > 0) {
                    // Trigger AI generation with context
                    try {
                        $aiService = app(\App\Services\AI\AIService::class);
                        $generated = $aiService->generateQuestions($subject, $missing, $context);

                        foreach ($generated as $nq) {
                            if (!empty($nq['statement'])) {
                                $createdQ = Question::create([
                                    'type' => 'concurso',
                                    // 'subject' removed
                                    'difficulty' => $nq['difficulty'] ?? 'medium',
                                    'year' => date('Y'),
                                    'statement' => $nq['statement'],
                                    'explanation' => $nq['explanation'] ?? null,
                                    'source' => 'ai_generated',
                                    'organization' => $context['organization'][0] ?? null,
                                    'institution' => $context['institution'][0] ?? null,
                                    'role' => $context['role'][0] ?? null,
                                ]);

                                // Create Alternatives via relation
                                if (!empty($nq['alternatives']) && is_array($nq['alternatives'])) {
                                    $correct = strtoupper($nq['correct_answer'] ?? 'A');
                                    foreach ($nq['alternatives'] as $label => $content) {
                                        $createdQ->alternatives()->create([
                                            'label' => strtoupper($label),
                                            'content' => $content,
                                            'is_correct' => strtoupper($label) === $correct,
                                        ]);
                                    }
                                }

                                $subjectModel = \App\Models\Subject::firstOrCreate(
                                ['name' => $subject],
                                ['slug' => \Illuminate\Support\Str::slug($subject), 'type' => 'concurso']
                                );
                                $createdQ->subjects()->attach($subjectModel->id);
                                $finalQuestions->push($createdQ);
                            }
                        }
                    }
                    catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Concurso AI Failed: " . $e->getMessage());
                    }
                }

                continue; // Skip ENEM logic
            }

            // 1. Calculate Quotas (STRICT 90/10 split)
            $countGenTarget = (int)ceil($subjectTotal * 0.10);
            $countRealTarget = $subjectTotal - $countGenTarget;

            // 2. Fetch Pools from Availability Service
            $availability = $this->availabilityService->getAvailableQuestions($user, $subject, $subjectTotal);
            $realPool = $availability['real'];
            $aiPool = $availability['ai'];

            $subjectQuestions = collect();

            // 3. Pick Real content up to their target
            $pickedReal = $realPool->take($countRealTarget);
            $subjectQuestions = $subjectQuestions->merge($pickedReal);

            // 4. Pick Existing IA content up to their target
            $pickedAi = $aiPool->take($countGenTarget);
            $subjectQuestions = $subjectQuestions->merge($pickedAi);
            $remainingAiPool = $aiPool->diff($pickedAi);

            // 5. Check if we have gaps
            $missingTotal = $subjectTotal - $subjectQuestions->count();

            if ($missingTotal > 0) {
                // If we are missing questions, it's either because:
                // a) Real questions were exhausted (need AI fallback)
                // b) Existing AI was exhausted (need Generation)

                // First, try to fill "Exhaustion gaps" with REMAINING existing AI from pool
                // This covers cases where we wanted 90 Real but only found 50.
                $extraAiFromPool = $remainingAiPool->take($missingTotal);
                $subjectQuestions = $subjectQuestions->merge($extraAiFromPool);

                $missingTotal = $subjectTotal - $subjectQuestions->count();

                // If still missing, we MUST generate.
                // This covers the remaining IA quota AND the Real exhaustion exhaustion.
                if ($missingTotal > 0) {
                    try {
                        $aiService = app(\App\Services\AI\AIService::class);
                        $batchSize = 5;
                        $attempts = 0;
                        $maxAttempts = 3;

                        while ($missingTotal > 0 && $attempts < $maxAttempts) {
                            $attempts++;
                            $chunkCount = min($batchSize, $missingTotal);
                            $newQuestions = $aiService->generateQuestions($subject, $chunkCount);

                            if (empty($newQuestions))
                                break;

                            foreach ($newQuestions as $nq) {
                                if (!empty($nq['statement']) && !empty($nq['alternatives'])) {
                                    $createdQ = Question::create([
                                        'type' => $type,
                                        // 'subject' removed
                                        'difficulty' => $nq['difficulty'] ?? 'medium',
                                        'year' => $nq['year'] ?? rand(2015, 2025),
                                        'statement' => $nq['statement'],
                                        'explanation' => $nq['explanation'] ?? null,
                                        'source' => 'ai_generated',
                                    ]);

                                    // Create Alternatives via relation
                                    if (!empty($nq['alternatives']) && is_array($nq['alternatives'])) {
                                        $correct = strtoupper($nq['correct_answer'] ?? 'A');
                                        foreach ($nq['alternatives'] as $label => $content) {
                                            $createdQ->alternatives()->create([
                                                'label' => strtoupper($label),
                                                'content' => $content,
                                                'is_correct' => strtoupper($label) === $correct,
                                            ]);
                                        }
                                    }

                                    $subjectModel = \App\Models\Subject::firstOrCreate(
                                    ['name' => $subject],
                                    ['slug' => \Illuminate\Support\Str::slug($subject), 'type' => $type]
                                    );
                                    $createdQ->subjects()->attach($subjectModel->id);

                                    $subjectQuestions->push($createdQ);
                                    $missingTotal--;
                                    if ($missingTotal <= 0)
                                        break;
                                }
                            }
                        }
                    }
                    catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("AI Generation failed for subject $subject: " . $e->getMessage());
                    }
                }
            }

            // 6. Absolute Fallback: Only if AI Generation FAILED and pool is empty, 
            // take whatever Real is left (ignoring "last 10" filter as emergency measure)
            if ($subjectQuestions->count() < $subjectTotal) {
                $emergencyNeeded = $subjectTotal - $subjectQuestions->count();
                $emergencyReal = Question::whereHas('subjects', function ($q) use ($subject) {
                    $q->where('name', $subject);
                })
                    ->where(function ($q) {
                    $q->where('source', 'enem_real_2009_2023')
                        ->orWhere('source', 'manual')
                        ->orWhere('source', 'enem_api'); // Added enem_api just in case
                })
                    ->whereNotIn('id', $subjectQuestions->pluck('id'))
                    ->inRandomOrder()
                    ->limit($emergencyNeeded)
                    ->get();

                $subjectQuestions = $subjectQuestions->merge($emergencyReal);
            }

            $finalQuestions = $finalQuestions->merge($subjectQuestions->take($subjectTotal));
        }

        return $finalQuestions;
    }
}
