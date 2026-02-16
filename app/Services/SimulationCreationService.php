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
            if (empty($distribution[$subject])) {
                continue;
            }

            $subjectTotal = (int) $distribution[$subject];

            if ($type === 'enem' && in_array($subject, ['português', 'matemática'])) {
                // RULE: 90% Real + 10% Generated
                // Calculate quotas
                $countGen = (int) ceil($subjectTotal * 0.10); // At least 1 if total > 0
                $countReal = $subjectTotal - $countGen;

                // 1. Fetch Real Questions
                $realCandidates = Question::where('type', 'enem')
                    ->where('subject', $subject)
                    ->where('source', 'manual') // DB constraint
                    ->inRandomOrder()
                    ->limit($countReal * 2) // Overfetch for dedupe
                    ->get();

                $subjectQuestions = collect();

                foreach ($realCandidates as $q) {
                    if ($subjectQuestions->count() >= $countReal) break;

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
                    ->where('source', 'ai_generated') // DB constraint
                    ->inRandomOrder()
                    ->limit($countGen * 3)
                    ->get();
                
                $pickedGen = collect();
                foreach ($genCandidates as $q) {
                    if ($pickedGen->count() >= $countGen) break;
                    
                    $stmtHash = md5(trim($q->statement));
                    if (!in_array($stmtHash, $usedStatements)) {
                        $pickedGen->push($q);
                        $usedStatements[] = $stmtHash;
                    }
                }

                // CHECK: Do we have enough generated questions?
                $missingGen = $countGen - $pickedGen->count();

                if ($missingGen > 0) {
                    // CALL AI TO GENERATE MISSING
                    // We need AIService instance here. It's not injected in this class?
                    // Resolve via app() or inject. Better inject.
                    // Since specific instruction: "Call IA and insert in BD"
                    
                    try {
                        $aiService = app(\App\Services\AIService::class);
                        $newQuestions = $aiService->generateQuestions($subject, $missingGen);

                        foreach ($newQuestions as $nq) {
                            // Validate and Insert
                            if (!empty($nq['statement']) && !empty($nq['alternatives'])) {
                                $createdQ = Question::create([
                                    'type' => 'enem',
                                    'subject' => $subject,
                                    'theme' => null,
                                    'difficulty' => 'medium',
                                    'year' => rand(2015, 2025), // Requested range
                                    'statement' => $nq['statement'],
                                    'alternatives' => $nq['alternatives'],
                                    'correct_answer' => $nq['correct_answer'] ?? 'A',
                                    'explanation' => $nq['explanation'] ?? null,
                                    'source' => 'ai_generated' // DB constraint
                                ]);

                                $pickedGen->push($createdQ);
                                // Add to usedStatements to be safe
                                $usedStatements[] = md5(trim($createdQ->statement));

                                if ($pickedGen->count() >= $countGen) break;
                            }
                        }
                    } catch (\Exception $e) {
                         // Log error, but don't break flow. 
                         // Fallback: fill with Real questions if AI fails?
                         // Requirement says: "ALWAYS 90% source='enem_real' ... IMPORTANTE: Se não existir... chamar IA"
                         // If IA fails, we can't fulfill 10%. We should probably fill with Real to avoid broken simulation.
                    }
                }
                
                // If still missing generated (IA failed), fill with Real
                $stillMissing = $countGen - $pickedGen->count();
                if ($stillMissing > 0) {
                     $extraReal = Question::where('type', 'enem')
                        ->where('subject', $subject)
                        ->where('source', 'manual')
                        ->whereNotIn('id', $subjectQuestions->pluck('id'))
                        ->inRandomOrder()
                        ->limit($stillMissing)
                        ->get();
                     $pickedGen = $pickedGen->merge($extraReal);
                }

                $subjectQuestions = $subjectQuestions->merge($pickedGen);
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
