<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PostRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    /** @test */
    public function registration_with_paid_plan_redirects_to_checkout_welcome()
    {
        $response = $this->get('/register?plan=basic');
        $response->assertStatus(200);
        $this->assertEquals('basic', session('selected_plan'));

        $response = $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('checkout.welcome'));
    }

    /** @test */
    public function registration_with_free_plan_redirects_to_dashboard()
    {
        $response = $this->get('/register?plan=free');
        $response->assertStatus(200);
        $this->assertEquals('free', session('selected_plan'));

        $response = $this->post('/register', [
            'name' => 'Free User',
            'email' => 'free@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function registration_without_plan_redirects_to_dashboard()
    {
        $response = $this->post('/register', [
            'name' => 'No Plan User',
            'email' => 'noplan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function accessing_checkout_welcome_without_plan_in_session_redirects_to_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('checkout.welcome'));
        $response->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function skip_clears_session_and_redirects_to_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['selected_plan' => 'basic']);

        $response = $this->get(route('checkout.skip'));
        $response->assertRedirect(route('dashboard'));
        $this->assertFalse(session()->has('selected_plan'));
    }
}
