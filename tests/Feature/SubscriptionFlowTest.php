<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock AsaasService
        $this->mock(AsaasService::class , function ($mock) {
            $mock->shouldReceive('createSubscription')->andReturn([
                'id' => 'sub_mock_123'
            ]);
            $mock->shouldReceive('getFirstPendingPayment')->andReturn([
                'id' => 'pay_mock_123'
            ]);
            $mock->shouldReceive('getPixQrCode')->andReturn([
                'payload' => 'pix_code_abcd',
                'encodedImage' => 'base64_image'
            ]);
        });
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_checkout_page_loads_for_plan()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->plus()->create();

        $response = $this->actingAs($user)->get(route('plans.checkout', $plan));

        $response->assertStatus(200);
        $response->assertSee($plan->name);
    }

    public function test_user_can_subscribe_via_credit_card()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->plus()->create();

        $response = $this->actingAs($user)->post(route('plans.store', $plan), [
            'payment_method' => 'credit_card',
            'card_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2030',
            'card_ccv' => '123',
            'cpf' => '12345678900',
        ]);

        $response->assertStatus(200); // Renders success view
        $response->assertViewIs('subscriptions.success');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway_id' => 'sub_mock_123',
            'status' => 'pending' // Initial local status
        ]);
    }

    public function test_user_can_subscribe_via_pix()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->plus()->create();

        $response = $this->actingAs($user)->post(route('plans.store', $plan), [
            'payment_method' => 'pix',
            'cpf' => '12345678900',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('subscriptions.pending'); // Shows QR Code
        $response->assertSee('pix_code_abcd');
    }
}
