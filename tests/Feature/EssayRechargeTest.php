<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Essay;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class EssayRechargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed Plans if needed or create manually
        // We assume Plan factory or manual creation
    }

    public function test_recharge_button_visibility()
    {
        // 1. Create User with Plus Plan
        $plan = Plan::factory()->create([
            'name' => 'Plus',
            'essays_limit' => 15,
            'slug' => 'plus'
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'essay_credits' => 0]);

        // 2. User has limits remaining (0 used)
        $response = $this->actingAs($user)->get(route('essays.index'));
        $response->assertDontSee('Recarregar');
        $response->assertSee('Nova Redação');

        // 3. Consume all limits
        // Create 15 submitted essays
        Essay::factory()->count(15)->create([
            'user_id' => $user->id,
            'submitted_at' => now(),
            'status' => 'completed'
        ]);

        // 4. User has NO limits remaining
        $response = $this->actingAs($user)->get(route('essays.index'));
        $response->assertSee('Recarregar (+15) - R$ 20,00');
        $response->assertDontSee('Nova Redação');
        // Logic: if canCreate is false, show disabled button. 
        // AND if Plus/Basic, show Recharge button.
    }

    public function test_basic_plan_recharge_values()
    {
        $plan = Plan::factory()->create([
            'name' => 'Plano Básico',
            'essays_limit' => 2,
            'slug' => 'basic'
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        // Consume limits
        Essay::factory()->count(2)->create([
            'user_id' => $user->id,
            'submitted_at' => now()
        ]);

        $response = $this->actingAs($user)->get(route('essays.index'));
        $response->assertSee('Recarregar');
        $response->assertSee('+2');
        $response->assertSee('R$ 5,00');
    }

    public function test_checkout_page_loads_correctly()
    {
        $this->mock(AsaasService::class);

        $plan = Plan::factory()->create(['name' => 'Plus']);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $response = $this->actingAs($user)->get(route('recharge'));
        $response->assertStatus(200);
        $response->assertSee('Confirmar Recarga');
        $response->assertSee('R$ 20,00');
    }

    public function test_successful_credit_card_recharge_increments_credits()
    {
        $plan = Plan::factory()->create(['name' => 'Plus', 'essays_limit' => 15]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'essay_credits' => 0]);

        // Mock AsaasService
        $this->mock(AsaasService::class, function ($mock) {
            $mock->shouldReceive('createOneTimePayment')
                ->once()
                ->andReturn(['id' => 'pay_123', 'status' => 'CONFIRMED']);
            $mock->shouldReceive('getOrCreateCustomer')->andReturn('cus_123');
        });

        $response = $this->actingAs($user)->post(route('recharge'), [
            'payment_method' => 'credit_card',
            'card_name' => 'Test User',
            'card_number' => '1234123412341234',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2030',
            'card_ccv' => '123',
            'cpf' => '12345678900'
        ]);

        $response->assertRedirect(route('essays.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'essay_credits' => 15
        ]);

        // Verify user can now create essay
        $user->refresh();
        $this->assertTrue($user->canCreateEssay());
    }
}
