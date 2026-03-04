<?php

namespace App\Jobs;

use App\Models\StudyPlan;
use App\Services\Study\StudyPlanGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateStudyPlanJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $studyPlanId;

    public function __construct(int $studyPlanId)
    {
        $this->studyPlanId = $studyPlanId;
    }

    public function handle(StudyPlanGenerator $generator): void
    {
        $plan = StudyPlan::find($this->studyPlanId);

        if (!$plan) {
            Log::error("StudyPlan not found for generation: {$this->studyPlanId}");
            return;
        }

        try {
            $plan->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $generator->generateContent($plan);

        } catch (\Throwable $e) {
            $this->markAsFailed($plan, $e->getMessage());
            throw $e; // Re-throw to allow Laravel to handle retry/failure logic
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $plan = StudyPlan::find($this->studyPlanId);
        if ($plan) {
            $this->markAsFailed($plan, "Falha crítica na fila: " . $exception->getMessage());
        }
    }

    protected function markAsFailed(StudyPlan $plan, string $message): void
    {
        Log::error("Failed to generate study plan {$plan->id}: " . $message);

        $plan->update([
            'status' => 'failed',
            'error_message' => 'Ocorreu um erro no processamento do seu plano. Por favor, tente novamente.',
            'finished_at' => now(),
        ]);
    }
}
