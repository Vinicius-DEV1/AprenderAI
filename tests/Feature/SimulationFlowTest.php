<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Question;
use App\Models\Simulation;
use App\Models\Subject;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimulationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock AIService globally to prevent real API calls
        $this->mock(\App\Services\AI\AIService::class);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_user_can_view_create_simulation_page()
    {
        $user = User::factory()->withPlusPlan()->create();

        $response = $this->actingAs($user)->get(route('simulations.create'));

        $response->assertStatus(200);
        // $response->assertViewIs('simulations.create'); // MIGRATED TO REACT
    }

    public function test_simulation_limit_enforced_by_plan()
    {
        // User with free plan (limit 5) and 5 used simulations
        $user = User::factory()->withFreePlan()->create([
            'simulations_used_this_month' => 5
        ]);

        $response = $this->actingAs($user)->get(route('simulations.create'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
    }

    public function test_user_can_create_enem_simulation()
    {
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();

        // Create subjects for the simulation
        \App\Models\Subject::create(['name' => 'Matemática', 'slug' => 'matematica', 'type' => 'enem']);
        \App\Models\Subject::create(['name' => 'Português', 'slug' => 'portugues', 'type' => 'enem']);

        $response = $this->actingAs($user)->post(route('simulations.store'), [
            'type' => 'enem',
            'mode' => 'training',
            'total_questions' => 40,
            'subject_distribution' => ['Matemática' => 20, 'Português' => 20],
        ]);

        // Assert redirect to show (loading screen)
        $simulation = Simulation::first();
        $this->assertNotNull($simulation);
        $response->assertRedirect(route('simulations.show', $simulation));

        // Assert job dispatch
        Queue::assertPushed(\App\Jobs\GenerateSimulationQuestions::class);
    }

    public function test_user_can_save_answer()
    {
        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create();
        $question = Question::factory()->create();

        // Relation
        $simulation->answers()->create([
            'question_id' => $question->id,
            'user_answer' => null
        ]);

        $response = $this->actingAs($user)->post(route('simulations.answer', [$simulation]), [
            'question_id' => $question->id,
            'answer' => 'A',
            'time_spent' => 10
        ]);

        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('simulation_answers', [
            'simulation_id' => $simulation->id,
            'question_id' => $question->id,
            'user_answer' => 'A'
        ]);
    }

    public function test_user_can_finish_simulation()
    {
        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create(['status' => 'in_progress']);

        $response = $this->actingAs($user)->post(route('simulations.finish', $simulation));

        $response->assertRedirect(route('simulations.result', $simulation));

        $simulation->refresh();
        $this->assertEquals('finished', $simulation->status);
        $this->assertNotNull($simulation->finished_at);
    }

    public function test_result_page_loads_correctly()
    {
        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->finished()->create();

        $response = $this->actingAs($user)->get(route('simulations.result', $simulation));

        $response->assertStatus(200);
        // $response->assertViewIs('simulations.result'); // MIGRATED TO REACT
    }

    public function test_cannot_view_other_users_simulation()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $simulation = Simulation::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('simulations.show', $simulation));

        $response->assertStatus(403);
    }
}
