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

        \Illuminate\Support\Facades\Log::info("DEBUG: Starting processSimulationQuestions [v4 - relaxed validation]. Simulation: {$simulation->id}");

        $questions = $this->selectQuestions($simulation->user, $type, $total, $distribution, $data);

        // Relaxed validation: Instead of throwing exception and breaking the flow, 
        // we log the warning and proceed with what we have.
        if ($questions->count() < $total) {
            \Illuminate\Support\Facades\Log::warning("Simulation created with fewer questions than requested. Requested: $total | Found: {$questions->count()}. User: {$simulation->user->id}");

            // Only throw if absolutely EMPTY (which should be rare with repetition fallback)
            if ($questions->isEmpty()) {
                throw new \Exception("Quantidade insuficiente de questões disponíveis para este tipo de simulado.");
            }
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

            // Normalization of common subject names to match Database exactly (Resiliency)
            $subjectOrig = $subject;
            $subjectUpper = mb_strtoupper(trim($subject), 'UTF-8');
            // Remove accents for comparison
            $subjectSanitized = str_replace(
                ['Á', 'À', 'Â', 'Ã', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
                ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
                $subjectUpper
            );

            // Map to related names (Aggregation)
            $searchNames = [$subjectUpper, $subjectSanitized];
            if (str_contains($subjectSanitized, 'PORTUGU')) {
                $searchNames = array_merge($searchNames, ['LINGUA PORTUGUESA', 'LÍNGUA PORTUGUESA', 'PORTUGUES', 'PORTUGUÊS']);
            } elseif (str_contains($subjectSanitized, 'MATEM')) {
                $searchNames = array_merge($searchNames, ['MATEMATICA', 'MATEMÁTICA']);
            } elseif (str_contains($subjectSanitized, 'FISIC')) {
                $searchNames = array_merge($searchNames, ['FISICA', 'FÍSICA']);
            } elseif (str_contains($subjectSanitized, 'QUIMIC')) {
                $searchNames = array_merge($searchNames, ['QUIMICA', 'QUÍMICA']);
            } elseif (str_contains($subjectSanitized, 'HISTOR')) {
                $searchNames = array_merge($searchNames, ['HISTORIA', 'HISTÓRIA']);
            }

            $searchNames = array_unique($searchNames);

            \Illuminate\Support\Facades\Log::info("Processing Subject: " . implode(', ', $searchNames) . " (Orig: $subjectOrig) | Total Needed: $subjectTotal | Type: $type");

            // ----------------------------------------------------------------
            // CONCURSO PATH
            // ----------------------------------------------------------------
            if ($type === 'concurso') {
                $query = Question::published()->whereHas('subjects', function ($q) use ($searchNames) {
                    $q->whereIn('name', $searchNames);
                });

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

                // Fallback: Repetition
                if ($subjectQuestions->count() < $subjectTotal) {
                    $missing = $subjectTotal - $subjectQuestions->count();
                    $extra = Question::published()->whereHas('subjects', function ($q) use ($searchNames) {
                        $q->whereIn('name', $searchNames);
                    })
                        ->whereNotIn('id', array_merge($finalQuestions->pluck('id')->toArray(), $subjectQuestions->pluck('id')->toArray(), $lastSeenIds))
                        ->inRandomOrder()
                        ->limit($missing)
                        ->get();
                    $subjectQuestions = $subjectQuestions->merge($extra);
                }

                $finalQuestions = $finalQuestions->merge($subjectQuestions);
                continue;
            }

            // ----------------------------------------------------------------
            // ENEM PATH
            // ----------------------------------------------------------------
            $preset = \App\Models\SimulationPreset::where('type', $type)->where('is_active', true)->first();
            $aiRatio = 0.10;

            if ($preset) {
                $rule = $preset->rules()->where('category', 'subject_distribution')->first();
                $aiRatio = $rule->configuration['ai_ratio'] ?? 0.10;
            }

            $countGenTarget = (int) ceil($subjectTotal * $aiRatio);
            $countRealInitial = $subjectTotal - $countGenTarget;

            $ignoredIds = $this->getLastSeenQuestionIds($user);
            $alreadyPickedIds = $finalQuestions->pluck('id')->toArray();

            // 1. Initial Real Questions (No Repeat)
            $realQuestions = Question::published()->whereHas('subjects', function ($q) use ($searchNames) {
                $q->whereIn('name', $searchNames);
            })
                ->where('type', 'enem')
                ->where(function ($q) {
                    $q->where('source', 'enem_real_2009_2023')
                        ->orWhere('source', 'manual')
                        ->orWhere('source', 'enem_api')
                        ->orWhere('source', 'api');
                })
                ->whereNotIn('id', array_merge($ignoredIds, $alreadyPickedIds))
                ->inRandomOrder()
                ->limit($countRealInitial)
                ->get();

            // 2. Pre-generated AI questions from DB
            $aiQuestions = Question::published()->whereHas('subjects', function ($q) use ($searchNames) {
                $q->whereIn('name', $searchNames);
            })
                ->where('type', 'enem')
                ->where('source', 'ai_generated')
                ->whereNotIn('id', array_merge($ignoredIds, $alreadyPickedIds, $realQuestions->pluck('id')->toArray()))
                ->inRandomOrder()
                ->limit($countGenTarget)
                ->get();

            $subjectQuestions = $realQuestions->merge($aiQuestions);

            // 3. Fallback: Repetition (Strict: DO NOT ignore ignoredIds)
            $missing = $subjectTotal - $subjectQuestions->count();
            if ($missing > 0) {
                $extraQuestions = Question::published()->whereHas('subjects', function ($q) use ($searchNames) {
                    $q->whereIn('name', $searchNames);
                })
                    ->where('type', 'enem')
                    ->whereNotIn('id', array_merge($alreadyPickedIds, $subjectQuestions->pluck('id')->toArray(), $ignoredIds))
                    ->inRandomOrder()
                    ->limit($missing)
                    ->get();

                $subjectQuestions = $subjectQuestions->merge($extraQuestions);
            }

            $finalQuestions = $finalQuestions->merge($subjectQuestions->take($subjectTotal));
        }

        return $finalQuestions;
    }
}
