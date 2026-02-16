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
     * Create a new simulation with selected questions and answers.
     */
    public function createSimulation(User $user, array $data): Simulation
    {
        set_time_limit(300);
        return DB::transaction(function () use ($user, $data) {
            $total = (int) $data['total_questions'];
            $distribution = $data['subject_distribution'] ?? [];
            $type = $data['type'];

            // 1. Create Simulation Record
            $simulation = Simulation::create([
                'user_id' => $user->id,
                'type' => $type,
                'configuration' => [
                    'questions' => $total,
                    'subject_distribution' => $distribution,
                    'include_essay' => $data['include_essay'] ?? false,
                    'time_limit' => $type === 'enem'
                        ? (($data['include_essay'] ?? false) ? 19800 : 16200)
                        : (int) ($data['custom_time'] ?? 10800),
                ],
                'status' => 'pending',
            ]);

            // 2. Select Questions
            $questions = $this->selectQuestions($type, $total, $distribution);

            // Validation: Ensure we found enough questions
            if ($questions->count() !== $total) {
                // Rollback will happen automatically if we throw exception
                throw new \Exception("Banco insuficiente para montar {$total} questões com essa distribuição. Foram selecionadas {$questions->count()}.");
            }

            // 3. Create Answers
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

            // 4. Update User Stats and Start Simulation
            $user->incrementSimulationUsage();
            $simulation->startSimulation();

            return $simulation;
        });
    }

    /**
     * Select questions respecting business rules.
     */
    protected function selectQuestions(string $type, int $total, array $distribution): Collection
    {
        $finalQuestions = collect();
        $usedStatements = []; // Global deduplication by statement hash

        // 1. Define Subject Order
        if ($type === 'enem') {
            $orderedSubjects = ['português', 'matemática'];
            foreach (array_keys($distribution) as $s) {
                if (!in_array($s, $orderedSubjects)) {
                    $orderedSubjects[] = $s;
                }
            }
        } else {
            $orderedSubjects = array_keys($distribution);
        }

        foreach ($orderedSubjects as $subject) {
            $subjectTotal = (int) ($distribution[$subject] ?? 0);
            \Illuminate\Support\Facades\Log::info("Processing Subject: $subject | Total Needed: $subjectTotal");

            if ($subjectTotal <= 0) {
                continue;
            }

            // The original line is now redundant or can be kept if it's meant to re-cast.
            // $subjectTotal = (int) $distribution[$subject]; // This line was originally here

            if ($type === 'enem' && in_array($subject, ['português', 'matemática'])) {
                // RULE: 90% Real + 10% Generated
                // Calculate quotas
                $countGen = (int) ceil($subjectTotal * 0.10); // At least 1 if total > 0
                $countReal = $subjectTotal - $countGen;

                // 1. Fetch Real Questions
                // NOTE: Real questions are marked as 'concurso' type in DB but have the official ENEM source.
                $realCandidates = Question::where('subject', $subject)
                    ->where('source', 'enem_real_2009_2023') // Official ENEM Source
                    ->inRandomOrder()
                    ->limit($countReal * 2) // Overfetch for dedupe
                    ->get();

                \Illuminate\Support\Facades\Log::info("Real Candidates Found for $subject: " . $realCandidates->count());
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

                // If we didn't get enough real questions (unlikely given import), we might need to fallback?
                // For now, assume enough real questions exist.

                // 2. Fetch Generated Questions
                $genCandidates = Question::where('type', 'enem')
                    ->where('subject', $subject)
                    ->where('source', 'generated_system') // DB constraint
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
                \Illuminate\Support\Facades\Log::info("Picked Generated Candidates for $subject: " . $pickedGen->count());

                // CHECK: Do we have enough questions in total (Real + Gen)?
                // We need $subjectTotal, but we have $subjectQuestions + $pickedGen
                $currentTotal = $subjectQuestions->count() + $pickedGen->count();
                $missingGen = $subjectTotal - $currentTotal;

                if ($missingGen > 0) {
                    \Illuminate\Support\Facades\Log::info("Still missing $missingGen questions for $subject. Calling AI...");
                    try {
                        $aiService = app(\App\Services\AIService::class);

                        // Chunking AI Generation in batches of 5
                        $batchSize = 5;
                        $attempts = 0;
                        $maxAttempts = 10; // Safety limit
                        $consecutiveFailures = 0;

                        while (($pickedGen->count() + $subjectQuestions->count()) < $subjectTotal && $attempts < $maxAttempts) {
                            $attempts++;
                            $stillNeeded = $subjectTotal - ($pickedGen->count() + $subjectQuestions->count());
                            $chunkCount = min($batchSize, $stillNeeded);

                            $newQuestions = $aiService->generateQuestions($subject, $chunkCount);

                            if (empty($newQuestions)) {
                                $consecutiveFailures++;
                            } else {
                                $consecutiveFailures = 0;
                            }

                            foreach (($newQuestions ?? []) as $nq) {
                                // Validate and Insert
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
                                        'source' => 'generated_system' // DB constraint
                                    ]);

                                    $pickedGen->push($createdQ);
                                    $usedStatements[] = md5(trim($createdQ->statement));

                                    if (($pickedGen->count() + $subjectQuestions->count()) >= $subjectTotal)
                                        break 2;
                                }
                            }

                            if ($consecutiveFailures >= 2) {
                                \Illuminate\Support\Facades\Log::warning("Aborting AI generation for $subject after $consecutiveFailures consecutive failures.");
                                break;
                            }

                        }
                    } catch (\Exception $e) {
                        $consecutiveFailures++;
                        \Illuminate\Support\Facades\Log::error("Failed to generate questions: " . $e->getMessage());

                        if ($consecutiveFailures >= 2) {
                            \Illuminate\Support\Facades\Log::warning("Aborting AI generation for $subject after $consecutiveFailures consecutive failures (Exception).");
                            break;
                        }
                    }
                }

                \Illuminate\Support\Facades\Log::info("Picked for $subject: Real=" . ($subjectQuestions->count()) . " | Gen=" . $pickedGen->count());

                $subjectQuestions = $subjectQuestions->merge($pickedGen);

                // FINAL CHECK: If still missing (IA failed to deliver enough), fill with Real even if duplicates
                $stillMissing = $subjectTotal - $subjectQuestions->count();
                if ($stillMissing > 0) {
                    \Illuminate\Support\Facades\Log::warning("Still missing $stillMissing questions for $subject after AI fallback. Filling with available real questions.");
                    $extraReal = Question::where('subject', $subject)
                        ->where('source', 'enem_real_2009_2023')
                        ->whereNotIn('id', $subjectQuestions->pluck('id')) // AVOID DUPLICATES
                        ->inRandomOrder()
                        ->limit($stillMissing)
                        ->get();
                    $subjectQuestions = $subjectQuestions->merge($extraReal);
                }

                $finalQuestions = $finalQuestions->merge($subjectQuestions);

            } else {
                // Standard random selection for other types
                $candidates = Question::where('type', $type)
                    ->where('subject', $subject)
                    ->inRandomOrder()
                    ->limit($subjectTotal)
                    ->get();

                $finalQuestions = $finalQuestions->merge($candidates);
            }
        }

        return $finalQuestions;
    }
}
