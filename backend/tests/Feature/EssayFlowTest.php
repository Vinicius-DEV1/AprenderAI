<?php

namespace Tests\Feature;

use App\Models\Essay;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EssayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(AIService::class);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_user_can_create_essay()
    {
        $user = User::factory()->withPlusPlan()->create();

        $response = $this->actingAs($user)->post(route('essays.store'), [
            'type' => 'enem',
            'title' => 'A importância da leitura',
            'content' => 'Conteúdo da redação...',
            'time_limit' => 60,
        ]);

        $response->assertStatus(302);

        $essay = Essay::first();
        $this->assertNotNull($essay);
        // Controller sets title to 'Gerando tema...' initially
        $this->assertEquals('Gerando tema...', $essay->title);
    }

    public function test_essay_limits_enforced()
    {
        // Free user, limit 0
        $user = User::factory()->withFreePlan()->create();

        $response = $this->actingAs($user)->post(route('essays.store'), [
            'type' => 'enem',
            'title' => 'Teste',
            'content' => '...',
            'time_limit' => 60,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    public function test_user_can_submit_essay_for_correction()
    {
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();
        // Policy requires 'in_progress' to allow updates
        $essay = Essay::factory()->for($user)->create(['status' => 'in_progress']);

        $response = $this->actingAs($user)->post(route('essays.submit', $essay), [
            'content' => str_repeat('Texto da redação com tamanho suficiente para passar na validação. ', 5),
        ]);

        $response->assertRedirect();

        $essay->refresh();
        $this->assertNotNull($essay->submitted_at);
        $this->assertEquals('evaluating', $essay->status);

        Queue::assertPushed(\App\Jobs\EvaluateEssayJob::class);
    }

    public function test_others_cannot_view_essay()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $essay = Essay::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->get(route('essays.show', $essay));

        $response->assertStatus(403);
    }
}
