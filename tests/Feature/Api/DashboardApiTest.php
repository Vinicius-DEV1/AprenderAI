<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Simulation;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_api_returns_complete_payload()
    {
        $user = User::factory()->create(); // Implicitly has no plan/Free in some setups, or we can force it

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertStatus(200);

        // Assert structure
        $response->assertJsonStructure([
            'stats' => [
                'total_simulations',
                'total_essays',
                'total_questions_answered',
                'average_math_score',
                'average_portuguese_score',
            ],
            'recent_simulations',
            'subjectPerformance',
            'simulationLimit' => [
                'can_create',
                'limit',
                'used',
                'remaining'
            ],
            'active_study_plan'
        ]);
    }

    public function test_dashboard_api_handles_user_without_plan_gratis()
    {
        $user = User::factory()->create(['plan_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('simulationLimit.can_create', false);
        $response->assertJsonPath('simulationLimit.limit', 0);
        $response->assertJsonPath('simulationLimit.used', 0);
        $response->assertJsonPath('simulationLimit.remaining', 0);
    }
}
