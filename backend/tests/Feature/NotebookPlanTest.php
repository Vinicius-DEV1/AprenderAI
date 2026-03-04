<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Question;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotebookPlanTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function free_user_cannot_create_more_than_one_notebook()
    {
        // Cria um plano com preço 0 (gratuito) ou sem assinaturas ativas
        $plan = Plan::factory()->create(['price' => 0]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'role' => 'student']);

        $this->actingAs($user);

        // Primeiro caderno (deve passar)
        $response1 = $this->postJson('/api/v1/notebooks', [
            'name' => 'Meu Primeiro Caderno',
        ]);
        $response1->assertStatus(201);

        // Segundo caderno (deve ser bloqueado)
        $response2 = $this->postJson('/api/v1/notebooks', [
            'name' => 'Meu Segundo Caderno',
        ]);
        $response2->assertStatus(403)
            ->assertJsonFragment(['error_code' => 'limit_reached']);

        $this->assertDatabaseCount('notebooks', 1);
    }

    /** @test */
    public function paid_user_can_create_multiple_notebooks()
    {
        $plan = Plan::factory()->create(['price' => 50]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'role' => 'student']);
        // Simular que tem assinatura ativa
        \App\Models\Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_end' => now()->addDays(30),
        ]);

        $this->actingAs($user);

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/v1/notebooks', [
                'name' => "Caderno $i",
            ]);
            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('notebooks', 3);
    }

    /** @test */
    public function admin_can_create_multiple_notebooks()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/v1/notebooks', [
                'name' => "Caderno Admin $i",
            ]);
            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('notebooks', 3);
    }
}
