<?php

namespace App\Jobs;

use App\Models\Simulation;
use App\Services\SimulationCreationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSimulationQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $simulation;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(Simulation $simulation, array $data)
    {
        $this->simulation = $simulation;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(SimulationCreationService $service): void
    {
        Log::info("Job GenerateSimulationQuestions started for Simulation {$this->simulation->id}");

        try {
            // Process questions (select manual, generate AI)
            $service->processSimulationQuestions($this->simulation, $this->data);

            // Update status to pending (ready for user)
            $this->simulation->update(['status' => 'pending']);

            Log::info("Job GenerateSimulationQuestions completed for Simulation {$this->simulation->id}");

        }
        catch (\Throwable $e) {
            Log::error("Job GenerateSimulationQuestions failed for Simulation {$this->simulation->id}: " . $e->getMessage());
            $this->simulation->update(['status' => 'error']);
            throw $e;
        }
    }
}
