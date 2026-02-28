<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Simulation;
use App\Models\SimulationModel;
use App\Models\SimulationEngineRule;
use App\Models\SimulationDisciplineDistribution;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SimulationEngine
 *
 * Central service that reads rules from the DB and builds simulations
 * without any hardcoded numbers. Every knob (questions, time, AI ratio,
 * per-subject %, non-repetition window, difficulty mode) lives in
 * simulation_models → simulation_engine_rules → simulation_discipline_distributions.
 */
class SimulationEngine
{
    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Resolve a slug to a SimulationModel + its active rule + distributions.
     * Throws if the model is missing or has no active rule.
     */
    public function resolveModel(string $slug): array
    {
        $model = SimulationModel::where('slug', $slug)->where('ativo', true)->first();

        if (!$model) {
            throw new \RuntimeException("Simulation model '$slug' not found or inactive.");
        }

        $rule = SimulationEngineRule::where('model_id', $model->id)
            ->where('ativo', true)
            ->latest()
            ->first();

        if (!$rule) {
            throw new \RuntimeException("No active rule for simulation model '$slug'.");
        }

        $distributions = SimulationDisciplineDistribution::where('rule_id', $rule->id)
            ->orderBy('ordem')
            ->get();

        return compact('model', 'rule', 'distributions');
    }

    /**
     * Build the configuration array that gets stored in simulations.configuration.
     * This is the single source of truth so the front-end does not compute anything.
     */
    public function buildConfiguration(array $resolved, array $extra = []): array
    {
        /** @var SimulationEngineRule $rule */
        $rule = $resolved['rule'];

        /** @var \Illuminate\Support\Collection $distributions */
        $distributions = $resolved['distributions'];

        $subjectDist = $distributions->mapWithKeys(function ($d) use ($rule) {
            $qty = (int) round(($d->percentual / 100) * $rule->total_questoes);
            return [$d->disciplina => $qty];
        })->toArray();

        return array_merge([
            'questions' => $rule->total_questoes,
            'time_limit' => $rule->tempo_minutos * 60, // seconds
            'subject_distribution' => $subjectDist,
            'percentual_ia' => $rule->percentual_ia,
            'difficulty_mode' => $rule->difficulty_mode,
            'include_essay' => $extra['include_essay'] ?? false,
            'organization' => $extra['organization'] ?? [],
            'institution' => $extra['institution'] ?? [],
            'role' => $extra['role'] ?? [],
        ], $extra);
    }

    /**
     * Select and return questions following the engine rules.
     *
     * @param User   $user
     * @param array  $config   Output of buildConfiguration()
     * @param string $tipo     enem | concurso
     * @return Collection
     */
    public function selectQuestions(User $user, array $config, string $tipo): Collection
    {
        $total = $config['questions'];
        $distribution = $config['subject_distribution'];
        $aiRatio = ($config['percentual_ia'] ?? 10) / 100;
        $noRepeatLast = $config['nao_repetir_ultimos'] ?? 10;
        $diffMode = $config['difficulty_mode'] ?? 'balanceado';

        $excludedIds = $this->getRecentQuestionIds($user, $noRepeatLast);

        $finalQuestions = collect();

        foreach ($distribution as $subject => $subjectTotal) {
            if ($subjectTotal <= 0)
                continue;

            $countAiTarget = (int) ceil($subjectTotal * $aiRatio);
            $countRealTarget = $subjectTotal - $countAiTarget;

            $alreadyPicked = $finalQuestions->pluck('id')->toArray();
            $avoidIds = array_merge($excludedIds, $alreadyPicked);

            // 1. Fetch real (human-authored) questions
            $realQuery = Question::whereHas('subjects', fn($q) => $q->where('name', $subject))
                ->where('source', '!=', 'ai_generated')
                ->whereNotIn('id', $avoidIds);

            $realQuery = $this->applyDifficultyFilter($realQuery, $diffMode, $alreadyPicked);

            if ($tipo === 'concurso') {
                if (!empty($config['organization']))
                    $realQuery->whereIn('organization', $config['organization']);
                if (!empty($config['institution']))
                    $realQuery->whereIn('institution', $config['institution']);
                if (!empty($config['role']))
                    $realQuery->whereIn('role', $config['role']);
            }

            $realPool = $realQuery->inRandomOrder()->limit($countRealTarget)->get();
            $finalQuestions = $finalQuestions->merge($realPool);

            // 2. Fetch AI questions from DB (pre-generated)
            $aiPool = Question::whereHas('subjects', fn($q) => $q->where('name', $subject))
                ->where('source', 'ai_generated')
                ->whereNotIn('id', array_merge($avoidIds, $finalQuestions->pluck('id')->toArray()))
                ->inRandomOrder()
                ->limit($countAiTarget)
                ->get();
            $finalQuestions = $finalQuestions->merge($aiPool);

            // 3. Generate missing via AI if needed
            $missing = $subjectTotal - $finalQuestions->where(
                fn($q) => in_array($q->subjects->first()?->name, [$subject])
            )->count();

            if ($missing > 0) {
                $missing = $subjectTotal - collect($finalQuestions)->filter(function ($q) use ($subject) {
                    return $q->subjects->pluck('name')->contains($subject);
                })->count();

                if ($missing > 0) {
                    $generated = $this->generateAiQuestions($subject, $missing, $tipo, $config);
                    $finalQuestions = $finalQuestions->merge($generated);
                }
            }
        }

        return $finalQuestions->take($total);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Get question IDs seen in the last N finished simulations for the user.
     */
    private function getRecentQuestionIds(User $user, int $limit): array
    {
        $simIds = Simulation::where('user_id', $user->id)
            ->where('status', 'finished')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');

        if ($simIds->isEmpty())
            return [];

        return DB::table('simulation_answers')
            ->whereIn('simulation_id', $simIds)
            ->pluck('question_id')
            ->toArray();
    }

    /**
     * Apply difficulty ordering/filtering based on difficulty_mode.
     */
    private function applyDifficultyFilter($query, string $mode, array $alreadyPicked)
    {
        return match ($mode) {
            'progressivo' => $query->orderByRaw("FIELD(difficulty, 'easy', 'medium', 'hard')"),
            'balanceado' => $query->orderByRaw("RAND()"), // already random; can be fine-tuned
            default => $query,
        };
    }

    /**
     * AI generation fallback for missing questions.
     * Chunks requests into smaller batches (e.g. 5) to avoid LLM token limits/timeouts.
     */
    private function generateAiQuestions(string $subject, int $totalTarget, string $tipo, array $config): Collection
    {
        $generated = collect();
        $chunkSize = 5;
        $remaining = $totalTarget;

        try {
            /** @var \App\Services\AI\AIService $aiService */
            $aiService = app(\App\Services\AI\AIService::class);
            $context = array_intersect_key($config, array_flip(['organization', 'institution', 'role']));

            while ($remaining > 0) {
                $batchSize = min($remaining, $chunkSize);
                Log::info("SimulationEngine: Requesting chunk of $batchSize AI questions for $subject ($remaining left)");

                $newQs = $aiService->generateQuestions($subject, $batchSize, $context);

                if (empty($newQs)) {
                    Log::warning("SimulationEngine: AI batch returned empty for $subject. Abortion.");
                    break;
                }

                foreach ($newQs as $nq) {
                    if (empty($nq['statement']))
                        continue;

                    $createdQ = Question::create([
                        'type' => $tipo,
                        'difficulty' => $nq['difficulty'] ?? 'medium',
                        'year' => $nq['year'] ?? date('Y'),
                        'statement' => $nq['statement'],
                        'explanation' => $nq['explanation'] ?? null,
                        'source' => 'ai_generated',
                        'external_id' => 'ai_' . bin2hex(random_bytes(8)),
                        'organization' => $config['organization'][0] ?? null,
                        'institution' => $config['institution'][0] ?? null,
                        'role' => $config['role'][0] ?? null,
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

                    $subjectModel = Subject::firstOrCreate(
                        ['name' => $subject],
                        ['slug' => \Illuminate\Support\Str::slug($subject), 'type' => $tipo]
                    );
                    $createdQ->subjects()->attach($subjectModel->id);
                    $generated->push($createdQ);
                }

                $remaining -= count($newQs);

                // Safety break if AI keeps returning fewer than requested but not zero
                if (count($newQs) < $batchSize && $remaining > 0) {
                    Log::info("SimulationEngine: AI returned partial batch (" . count($newQs) . "/$batchSize). Continuing.");
                }
            }
        } catch (\Exception $e) {
            Log::warning("SimulationEngine AI generation failed [$subject/$totalTarget]: " . $e->getMessage());
        }

        return $generated;
    }
}
