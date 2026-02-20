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
                'started_at' => now(),
            ]);

            $generator->generateContent($plan);

        } catch (\Throwable $e) {
            Log::error("Failed to generate study plan {$this->studyPlanId}: " . $e->getMessage());

            $plan->update([
                'status' => 'failed',
                'error_message' => 'Erro interno ao gerar plano. Tente novamente.',
                'finished_at' => now(),
            ]);
        }
    }
}