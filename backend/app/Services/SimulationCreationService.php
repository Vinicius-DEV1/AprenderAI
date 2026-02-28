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
        $total = (int) $data['total_questions'];
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
                    : (int) ($data['custom_time'] ?? 10800),
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
        $total = (int) $data['total_questions'];
        $distribution = $data['subject_distribution'] ?? [];
        $type = $data['type'];

        $questions = $this->selectQuestions($simulation->user, $type, $total, $distribution, $data);

        // Strict validation: must have exactly the required number of questions
        if ($questions->count() < $total) {
            if ($questions->isEmpty()) {
                throw new \Exception("Quantidade insuficiente de quest\u00f5es dispon\u00edveis para este tipo de simulado.");
            }
            // Partial result: reject to avoid a broken simulation
            throw new \Exception(
                "Quantidade insuficiente de quest\u00f5es dispon\u00edveis para este tipo de simulado. "
                . "Solicitado: $total | Encontrado: {$questions->count()}."
            );
        }

        // Persistence (Inside Transaction - Fast)
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

            \Illuminate\Support\Facades\Log::info("Processing Subject: $subject | Total Needed: $subjectTotal | Type: $type");

            // ----------------------------------------------------------------
            // CONCURSO PATH
            // ----------------------------------------------------------------
            if ($type === 'concurso') {
                $query = Question::whereHas('subjects', function ($q) use ($subject) {
                    $q->where('name', $subject);
                });
                // No type restriction for concurso — can mix question types

                if (!empty($context['organization']))
                    $query->whereIn('organization', $context['organization']);
                if (!empty($context['institution']))
                    $query->whereIn('institution', $context['institution']);
                if (!empty($context['role']))
                    $query->whereIn('role', $context['role']);

                $avoidIds = array_merge($lastSeenIds, $finalQuestions->pluck('id')->toArray());
                if (!empty($avoidIds))
                    $query->whereNotIn('id', $avoidIds);

                $subjectQuestions = $query->inRandomOrder()->limit($subjectTotal)->get();
                $finalQuestions = $finalQuestions->merge($subjectQuestions);

                // AI Fallback for concurso gaps
                $missing = $subjectTotal - $subjectQuestions->count();
                if ($missing > 0) {
                    try {
                        $aiService = app(\App\Services\AI\AIService::class);
                        $generated = $aiService->generateQuestions($subject, $missing, $context);

                        foreach ($generated as $nq) {
                            if (!empty($nq['statement'])) {
                                $createdQ = Question::create([
                                    'type' => 'concurso',
                                    'difficulty' => $nq['difficulty'] ?? 'medium',
                                    'year' => date('Y'),
                                    'statement' => $nq['statement'],
                                    'explanation' => $nq['explanation'] ?? null,
                                    'source' => 'ai_generated',
                                    'external_id' => 'ai_' . bin2hex(random_bytes(8)),
                                    'organization' => $context['organization'][0] ?? null,
                                    'institution' => $context['institution'][0] ?? null,
                                    'role' => $context['role'][0] ?? null,
                                ]);

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
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Concurso AI Failed: " . $e->getMessage());
                    }
                }

                continue;
            }

            // ----------------------------------------------------------------
            // ENEM PATH (strict: only type='enem' questions)
            // ----------------------------------------------------------------
            $preset = \App\Models\SimulationPreset::where('type', $type)->where('is_active', true)->first();
            $aiRatio = 0.10;

            if ($preset) {
                $rule = $preset->rules()->where('category', 'subject_distribution')->first();
                $aiRatio = $rule->configuration['ai_ratio'] ?? 0.10;
            }

            $countGenTarget = (int) ceil($subjectTotal * $aiRatio);
            $countRealTarget = $subjectTotal - $countGenTarget;

            $ignoredIds = $this->getLastSeenQuestionIds($user);

            // Real ENEM questions only
            $realQuestions = Question::whereHas('subjects', function ($q) use ($subject) {
                $q->where('name', $subject);
            })
                ->where('type', 'enem')
                ->where(function ($q) {
                    $q->where('source', 'enem_real_2009_2023')
                        ->orWhere('source', 'manual')
                        ->orWhere('source', 'enem_api');
                })
                ->whereNotIn('id', $ignoredIds)
                ->inRandomOrder()
                ->limit($countRealTarget)
                ->get();

            // Pre-generated AI ENEM questions from DB
            $aiQuestions = Question::whereHas('subjects', function ($q) use ($subject) {
                $q->where('name', $subject);
            })
                ->where('type', 'enem')
                ->where('source', 'ai_generated')
                ->whereNotIn('id', $ignoredIds)
                ->inRandomOrder()
                ->limit($countGenTarget)
                ->get();

            $subjectQuestions = $realQuestions->merge($aiQuestions);

            // If still missing — trigger AI generation (chunked)
            $missing = $subjectTotal - $subjectQuestions->count();
            if ($missing > 0) {
                try {
                    $aiService = app(\App\Services\AI\AIService::class);
                    $chunkSize = 5;
                    $remaining = $missing;

                    while ($remaining > 0) {
                        $batch = min($remaining, $chunkSize);
                        $newQs = $aiService->generateQuestions($subject, $batch);

                        if (empty($newQs))
                            break;

                        foreach ($newQs as $nq) {
                            if (!empty($nq['statement']) && !empty($nq['alternatives'])) {
                                $createdQ = Question::create([
                                    'type' => 'enem',
                                    'difficulty' => $nq['difficulty'] ?? 'medium',
                                    'year' => $nq['year'] ?? rand(2015, 2025),
                                    'statement' => $nq['statement'],
                                    'explanation' => $nq['explanation'] ?? null,
                                    'source' => 'ai_generated',
                                    'external_id' => 'ai_' . bin2hex(random_bytes(8)),
                                ]);

                                $correct = strtoupper($nq['correct_answer'] ?? 'A');
                                foreach ($nq['alternatives'] as $label => $content) {
                                    $createdQ->alternatives()->create([
                                        'label' => strtoupper($label),
                                        'content' => $content,
                                        'is_correct' => strtoupper($label) === $correct,
                                    ]);
                                }

                                $subjectModel = \App\Models\Subject::firstOrCreate(
                                    ['name' => $subject],
                                    ['slug' => \Illuminate\Support\Str::slug($subject), 'type' => 'enem']
                                );
                                $createdQ->subjects()->attach($subjectModel->id);
                                $subjectQuestions->push($createdQ);
                                $remaining--;
                                if ($remaining <= 0)
                                    break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("ENEM AI Generation failed for $subject: " . $e->getMessage());
                }
            }

            $finalQuestions = $finalQuestions->merge($subjectQuestions->take($subjectTotal));
        }

        return $finalQuestions;
    }
}
