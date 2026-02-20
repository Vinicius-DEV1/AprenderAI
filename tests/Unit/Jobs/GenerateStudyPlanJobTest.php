<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\User;
use App\Models\StudyPlan;
use App\Models\Plan;
use App\Models\Simulation;
use App\Jobs\GenerateStudyPlanJob;
use App\Services\Study\StudyPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Illuminate\Support\Facades\Log;

class GenerateStudyPlanJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setupUserWithAccess()
    {
        $planModel = Plan::factory()->create(['name' => 'Plus']);
        $user = User::factory()->create(['plan_id' => $planModel->id]);
        return $user;
    }

    /** @test */
    public function it_calls_generator_and_logs_success()
    {
        $user = $this->setupUserWithAccess();
        $plan = StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => 'enem',
            'status' => 'processing',
            'hours_per_day' => 4
        ]);

        $mockGenerator = Mockery::mock(StudyPlanGenerator::class);
        $mockGenerator->shouldReceive('generateContent')
            ->once()
            ->with(Mockery::on(function ($arg) use ($plan) {
                return $arg->id === $plan->id;
            }));

        $job = new GenerateStudyPlanJob($plan->id);
        $job->handle($mockGenerator);

        $this->assertDatabaseHas('study_plans', [
            'id' => $plan->id,
            'started_at' => now(), // Mockery precision might fail here if not careful, but database has casts
            // started_at is updated before generation
        ]);
        
        // Since started_at is timestamp, exact match is hard. Just checking it's not null.
        $plan->refresh();
        $this->assertNotNull($plan->started_at);
    }

    /** @test */
    public function it_handles_failures_gracefully()
    {
        $user = $this->setupUserWithAccess();
        $plan = StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => 'enem',
            'status' => 'processing',
            'hours_per_day' => 4
        ]);

        $mockGenerator = Mockery::mock(StudyPlanGenerator::class);
        $mockGenerator->shouldReceive('generateContent')
            ->once()
            ->andThrow(new \Exception('AI Error'));

        $job = new GenerateStudyPlanJob($plan->id);
        $job->handle($mockGenerator);

        $this->assertDatabaseHas('study_plans', [
            'id' => $plan->id,
            'status' => 'failed',
            'error_message' => 'Erro interno ao gerar plano. Tente novamente.'
        ]);
    }
}