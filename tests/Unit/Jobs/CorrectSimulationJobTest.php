<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\User;
use App\Models\Simulation;
use App\Models\Question;
use App\Models\SimulationAnswer;
use App\Models\Plan;
use App\Jobs\CorrectSimulationJob;
use App\Services\AIService;
use App\Services\Study\StudyStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Illuminate\Support\Facades\Log;

class CorrectSimulationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function setupUserWithSimulation()
    {
        $planModel = Plan::factory()->create(['name' => 'Plus']);
        $user = User::factory()->create(['plan_id' => $planModel->id]);

        $question = Question::factory()->create();

        $sim = Simulation::factory()->create([
            'user_id' => $user->id,
            'status' => 'finished', // Valid status
        ]);

        $sim->answers()->create([
            'question_id' => $question->id,
            'user_answer' => 'A',
            'is_correct' => false // AI will determine correct
        ]);

        return [$user, $sim];
    }

    /** @test */
    public function it_corrects_simulation_and_updates_stats()
    {
        list($user, $sim) = $this->setupUserWithSimulation();

        // Mock AIService
        $mockAI = Mockery::mock(AIService::class);
        $mockAI->shouldReceive('correctSimulation')
            ->once()
            ->andReturn([
            'provider' => 'openai',
            'response' => [
                'errors_explanation' => [
                    ['question_id' => (string)$sim->answers->first()->question_id, 'explanation' => 'Test explanation']
                ]
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10]
        ]);

        // Mock StudyStatsService
        $mockStats = Mockery::mock(StudyStatsService::class);
        $mockStats->shouldReceive('updateUserStats')
            ->once()
            ->with(Mockery::on(function ($u) use ($user) {
            return $u->id === $user->id;
        }), Mockery::on(function ($s) use ($sim) {
            return $s->id === $sim->id;
        }));

        // Bind mocks
        $this->app->instance(StudyStatsService::class , $mockStats);

        $job = new CorrectSimulationJob($sim);
        $job->handle($mockAI);

        // Assertions
        $this->assertDatabaseHas('corrections', [
            'correctable_id' => $sim->id,
            'correctable_type' => Simulation::class ,
            'ai_provider' => 'openai'
        ]);
    }
}