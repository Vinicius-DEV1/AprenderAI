<?php

namespace App\Jobs;

use App\Models\StudyPlan;
use App\Services\StudyPlanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateStudyPlanJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $studyPlanId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $studyPlanId)
    {
        $this->studyPlanId = $studyPlanId;
    }

    /**
     * Execute the job.
     */
    public function handle(StudyPlanService $service): void
    {
        $plan = StudyPlan::find($this->studyPlanId);

        if (!$plan) {
            Log::error("StudyPlan not found for generation: {$this->studyPlanId}");
            return;
        }

        try {
            $plan->update([
                'started_at' => now(),
                // Status remains processing or we can exist explicit 'generating'
            ]);

            $service->generateContent($plan);

        } catch (\Throwable $e) {
            Log::error("Failed to generate study plan {$this->studyPlanId}: " . $e->getMessage());

            $plan->update([
                'status' => 'failed',
                'error_message' => 'Erro interno ao gerar plano. Tente novamente.', // User friendly message
                'finished_at' => now(),
            ]);
        }
    }
}
