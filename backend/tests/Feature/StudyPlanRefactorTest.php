<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StudyPlan;
use App\Models\UserTopicStat;
use App\Models\Plan;
use App\Models\Simulation;
use App\Models\SimulationAnswer;
use App\Services\Study\StudyPlanGenerator;
use App\Services\Study\StudyStatsService;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Mockery;

class StudyPlanRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed or mock necessary data
    }

    protected function setupUserWithAccess()
    {
        $planModel = Plan::factory()->create(['name' => 'Plus']);
        $user = User::factory()->create(['plan_id' => $planModel->id]);

        $sim = Simulation::factory()->create([
            'user_id' => $user->id,
            'status' => 'finished'
        ]);
        SimulationAnswer::factory()->count(1)->create(['simulation_id' => $sim->id]);

        return $user;
    }

    /** @test */
    public function it_can_generate_dashboard_data()
    {
        $user = $this->setupUserWithAccess();
        $plan = StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => 'enem',
            'status' => 'ready',
            'generated_at' => now(),
            'next_update_at' => now()->addDays(14),
            'hours_per_day' => 4,
        ]);

        $this->actingAs($user);

        // Populate stats for dashboard
        UserTopicStat::create([
            'user_id' => $user->id,
            'subject' => 'Matemática',
            'topic' => 'Algebra',
            'attempts' => 10,
            'correct' => 8,
            'accuracy' => 80,
            'last_attempt_at' => now()
        ]);

        $response = $this->get(route('study-plan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('study_plans.dashboard');
        $response->assertViewHas('plan');
        $response->assertViewHas('diagnostics');
    }

    /** @test */
    public function it_delegates_creation_to_generator()
    {
        $user = $this->setupUserWithAccess();
        $this->actingAs($user);

        $plan = new StudyPlan();
        $plan->forceFill(['id' => 1]);

        $mockGenerator = Mockery::mock(StudyPlanGenerator::class);
        $mockGenerator->shouldReceive('createPlaceholder')
            ->once()
            ->andReturn($plan);

        $this->app->instance(StudyPlanGenerator::class, $mockGenerator);

        $response = $this->post(route('study-plan.store'), [
            'exam_type' => 'enem',
            'hours_per_day' => 4
        ]);

        $response->assertRedirect(route('study-plan.index'));
    }

    /** @test */
    public function stats_service_calculates_accuracy_correctly()
    {
        $service = new StudyStatsService();
        $user = User::factory()->create();

        UserTopicStat::create([
            'user_id' => $user->id,
            'subject' => 'Matemática',
            'topic' => 'Total',
            'attempts' => 100,
            'correct' => 50,
            'accuracy' => 50,
            'last_attempt_at' => now()
        ]);

        $confidence = $service->getDataConfidence($user);

        $this->assertEquals('high', $confidence['level']);
    }
}
