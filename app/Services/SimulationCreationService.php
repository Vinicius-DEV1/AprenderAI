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
                $countReal = (int) floor($subjectTotal * 0.9);
                
                // Fetch Real Questions
                $realCandidates = Question::where('type', 'enem')
                    ->where('subject', $subject)
                    ->where('source', 'enem_real_2009_2023')
                    ->inRandomOrder()
                    ->limit($countReal * 2)
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

                // Fetch Generated/Other Questions for the remainder
                $neededGen = $subjectTotal - $subjectQuestions->count();
                
                $genCandidates = Question::where('type', 'enem')
                    ->where('subject', $subject)
                    ->where('source', 'generated_system')
                    ->inRandomOrder()
                    ->limit($neededGen * 3)
                    ->get();

                foreach ($genCandidates as $q) {
                    if ($subjectQuestions->count() >= $subjectTotal) break;

                    $stmtHash = md5(trim($q->statement));
                    if (!in_array($stmtHash, $usedStatements)) {
                        $subjectQuestions->push($q);
                        $usedStatements[] = $stmtHash;
                    }
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
