<?php

namespace Tests\Feature;

use App\Models\Essay;
use App\Models\Simulation;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimulationEssayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(\App\Services\AI\AIService::class);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    // =========================================================================
    // 1. Free user POSTs include_essay=1 → saved with include_essay=false
    // =========================================================================
    public function test_free_user_cannot_store_simulation_with_essay()
    {
        Queue::fake();

        $user = User::factory()->withFreePlan()->create();

        \App\Models\Subject::create(['name' => 'Matemática', 'slug' => 'matematica', 'type' => 'enem']);
        \App\Models\Subject::create(['name' => 'Português', 'slug' => 'portugues', 'type' => 'enem']);

        $this->actingAs($user)->post(route('simulations.store'), [
            'type' => 'enem',
            'mode' => 'training',
            'total_questions' => 40,
            'subject_distribution' => ['Matemática' => 20, 'Português' => 20],
            'include_essay' => '1',
        ]);

        $simulation = Simulation::first();
        $this->assertNotNull($simulation);
        $this->assertFalse((bool) ($simulation->configuration['include_essay'] ?? false));
    }

    // =========================================================================
    // 2. Paid user POSTs include_essay=1 → saved with include_essay=true
    // =========================================================================
    public function test_paid_user_can_store_simulation_with_essay()
    {
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();

        \App\Models\Subject::create(['name' => 'Matemática', 'slug' => 'matematica', 'type' => 'enem']);
        \App\Models\Subject::create(['name' => 'Português', 'slug' => 'portugues', 'type' => 'enem']);

        $this->actingAs($user)->post(route('simulations.store'), [
            'type' => 'enem',
            'mode' => 'training',
            'total_questions' => 40,
            'subject_distribution' => ['Matemática' => 20, 'Português' => 20],
            'include_essay' => true,
        ]);

        $simulation = Simulation::first();
        $this->assertNotNull($simulation);
        $this->assertTrue((bool) ($simulation->configuration['include_essay'] ?? false));
    }

    // =========================================================================
    // 3. Free user hitting storeForSimulation is forbidden (403)
    // =========================================================================
    public function test_free_user_cannot_access_store_for_simulation_route()
    {
        $user = User::factory()->withFreePlan()->create();
        $simulation = Simulation::factory()->for($user)->create([
            'configuration' => ['include_essay' => false],
        ]);

        $response = $this->actingAs($user)->post(route('simulations.essay.store', $simulation));

        $response->assertStatus(403);
    }

    // =========================================================================
    // 4. "Ir para Redação" route creates essay linked to simulation and redirects to topic
    // =========================================================================
    public function test_store_for_simulation_creates_essay_and_redirects_to_topic()
    {
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create([
            'configuration' => ['include_essay' => true],
        ]);

        $response = $this->actingAs($user)->post(route('simulations.essay.store', $simulation));

        $essay = Essay::where('simulation_id', $simulation->id)->first();
        $this->assertNotNull($essay);
        $this->assertEquals($user->id, $essay->user_id);
        $this->assertEquals('in_progress', $essay->status);


        $response->assertRedirect("/essays/{$essay->id}/topic");
    }

    // =========================================================================
    // 5. "Ir para Redação" route reuses existing essay (no duplicate)
    // =========================================================================
    public function test_store_for_simulation_redirects_to_existing_essay_at_write_step()
    {
        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create([
            'configuration' => ['include_essay' => true],
        ]);

        // Existing essay linked to this simulation, already has a topic
        $existingEssay = Essay::create([
            'user_id' => $user->id,
            'simulation_id' => $simulation->id,
            'type' => 'enem',
            'time_limit' => 60,
            'title' => 'Tema já gerado',
            'topic_description' => 'Descrição do tema aqui',
            'content' => '',
            'status' => 'in_progress',
            'topic_regen_count' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('simulations.essay.store', $simulation));

        // Should not create a second essay
        $this->assertEquals(1, Essay::where('simulation_id', $simulation->id)->count());


        $response->assertRedirect("/essays/{$existingEssay->id}/write");
    }

    // =========================================================================
    // 6. "Ir para Redação" route reuses existing submitted essay (show step)
    // =========================================================================
    public function test_store_for_simulation_redirects_to_existing_submitted_essay_at_show_step()
    {
        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create([
            'configuration' => ['include_essay' => true],
        ]);

        $submittedEssay = Essay::create([
            'user_id' => $user->id,
            'simulation_id' => $simulation->id,
            'type' => 'enem',
            'time_limit' => 60,
            'title' => 'Tema',
            'topic_description' => 'Descrição',
            'content' => str_repeat('x', 100),
            'status' => 'evaluating',
            'topic_regen_count' => 0,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('simulations.essay.store', $simulation));


        $response->assertRedirect("/essays/{$submittedEssay->id}");
    }

    // =========================================================================
    // 7. Result page includes essay link when essay exists + include_essay=true
    // =========================================================================
    public function test_result_page_shows_essay_button_when_linked_essay_exists()
    {
        $user = User::factory()->withPlusPlan()->create();

        $simulation = Simulation::factory()->for($user)->finished()->create([
            'configuration' => json_encode(['include_essay' => true]),
        ]);

        Essay::create([
            'user_id' => $user->id,
            'simulation_id' => $simulation->id,
            'type' => 'enem',
            'time_limit' => 60,
            'title' => 'Tema',
            'topic_description' => 'Descrição',
            'content' => '',
            'status' => 'in_progress',
            'topic_regen_count' => 0,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('simulations.result', $simulation));

        $response->assertStatus(200);
        $response->assertViewHas('linkedEssay');
        $linkedEssay = $response->viewData('linkedEssay');
        $this->assertNotNull($linkedEssay);
        $this->assertEquals($simulation->id, $linkedEssay->simulation_id);
    }

    // =========================================================================
    // 8. Result page does NOT include essay link when include_essay=false
    // =========================================================================
    public function test_result_page_has_no_essay_when_simulation_did_not_include_essay()
    {
        $user = User::factory()->withPlusPlan()->create();

        $simulation = Simulation::factory()->for($user)->finished()->create([
            'configuration' => json_encode(['include_essay' => false]),
        ]);

        $response = $this->actingAs($user)->get(route('simulations.result', $simulation));

        $response->assertStatus(200);
        $linkedEssay = $response->viewData('linkedEssay');
        $this->assertNull($linkedEssay);
    }
}
