<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Simulation;
use App\Models\StudyPlan;
use App\Models\User;
use App\Models\UserTopicStat;
use App\Models\Question;
use App\Models\SimulationAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\AIService;
use Mockery;

class StudyPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AIService to avoid external calls
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('generateStudyPlan')
                ->andReturn([
                    'overview' => 'Test Plan',
                    'weekly_schedule' => ['segunda' => ['Math']],
                    'focus_points' => ['Algebra'],
                    'methodology' => 'Pomodoro'
                ]);
            $mock->shouldReceive('hasActiveKey')->andReturn(true);
        });
    }

    public function test_free_user_sees_paywall()
    {
        $user = User::factory()->create(); // No plan

        $response = $this->actingAs($user)->get(route('study-plan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('study_plans.paywall');
    }

    public function test_plus_user_without_simulations_sees_empty_state()
    {
        $plan = Plan::factory()->create(['name' => 'Plus', 'features' => ['study_plan']]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($user)->get(route('study-plan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('study_plans.empty');
    }

    public function test_plus_user_with_simulation_sees_wizard_if_no_plan()
    {
        $plan = Plan::factory()->create(['name' => 'Plus', 'features' => ['study_plan']]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        // Create finished simulation
        $sim = Simulation::factory()->create(['user_id' => $user->id, 'status' => 'finished']);
        SimulationAnswer::factory()->create(['simulation_id' => $sim->id, 'is_correct' => true]);

        $response = $this->actingAs($user)->get(route('study-plan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('study_plans.wizard');
    }

    public function test_can_generate_study_plan()
    {
        $plan = Plan::factory()->create(['name' => 'Plus', 'features' => ['study_plan']]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $sim = Simulation::factory()->create(['user_id' => $user->id, 'status' => 'finished']);
        SimulationAnswer::factory()->create(['simulation_id' => $sim->id]);

        $response = $this->actingAs($user)->post(route('study-plan.store'), [
            'hours_per_day' => 4,
            'exam_type' => 'enem'
        ]);

        $response->assertRedirect(route('study-plan.index'));
        $this->assertDatabaseHas('study_plans', [
            'user_id' => $user->id,
            'hours_per_day' => 4
        ]);
    }

    public function test_rate_limit_generation()
    {
        $plan = Plan::factory()->create(['name' => 'Plus', 'features' => ['study_plan']]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        // Existing recent plan
        StudyPlan::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(5)
        ]);

        $sim = Simulation::factory()->create(['user_id' => $user->id, 'status' => 'finished']);
        SimulationAnswer::factory()->create(['simulation_id' => $sim->id]);

        $response = $this->actingAs($user)->post(route('study-plan.store'), [
            'hours_per_day' => 4,
            'exam_type' => 'enem'
        ]);

        $response->assertStatus(429); // Too Many Requests
    }
}
