<?php

namespace App\Jobs;

use App\Jobs\GenerateEssayTopicJob;
use App\Models\Essay;
use App\Models\Simulation;
use App\Services\SimulationCreationService;
use App\Services\SimulationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSimulationQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $simulation;
    protected $data;

    public function __construct(Simulation $simulation, array $data)
    {
        $this->simulation = $simulation;
        $this->data = $data;
    }

    public function handle(SimulationCreationService $legacyService, SimulationEngine $engine): void
    {
        Log::info("GenerateSimulationQuestions started for Simulation {$this->simulation->id}");

        try {
            if (!empty($this->data['model_slug'])) {
                // ── Engine-driven path ────────────────────────────────
                $tipo = $this->data['tipo'] ?? 'enem';
                $questions = $engine->selectQuestions(
                    $this->simulation->user,
                    $this->data,
                    $tipo
                );

                if ($questions->isEmpty()) {
                    throw new \Exception("Nenhuma questão encontrada para os critérios do modelo '{$this->data['model_slug']}'.");
                }

                DB::transaction(function () use ($questions) {
                    $now = now();
                    $rows = $questions->map(fn($q) => [
                        'simulation_id' => $this->simulation->id,
                        'question_id' => $q->id,
                        'user_answer' => null,
                        'is_correct' => false,
                        'time_spent' => 0,
                        'marked_for_review' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('simulation_answers')->insert($rows);
                    $this->simulation->user->incrementSimulationUsage();
                });
            } else {
                // ── Legacy path ───────────────────────────────────────
                $legacyService->processSimulationQuestions($this->simulation, $this->data);
            }

            // ── Essay creation (applies to both paths) ────────────────────────
            $includeEssay = $this->data['include_essay']
                ?? ($this->simulation->configuration['include_essay'] ?? false);

            if ($includeEssay) {
                $simType = $this->simulation->type ?? 'enem';
                $essay = Essay::create([
                    'user_id' => $this->simulation->user_id,
                    'simulation_id' => $this->simulation->id,
                    'type' => in_array($simType, ['enem', 'concurso']) ? $simType : 'enem',
                    'time_limit' => 90, // 90 min default for essay in simulations
                    'title' => 'Gerando tema...',
                    'content' => '',
                    'status' => 'in_progress',
                    'topic_regen_count' => 0,
                    'started_at' => now(),
                ]);

                GenerateEssayTopicJob::dispatch($essay->id);
                Log::info("GenerateSimulationQuestions: Essay {$essay->id} created and topic generation dispatched for Simulation {$this->simulation->id}");
            }

            $this->simulation->update(['status' => 'pending']);
            Log::info("GenerateSimulationQuestions completed for Simulation {$this->simulation->id}");

        } catch (\Throwable $e) {
            Log::error("GenerateSimulationQuestions failed for Simulation {$this->simulation->id}: " . $e->getMessage());
            $this->simulation->update(['status' => 'error']);
            throw $e;
        }
    }
}
