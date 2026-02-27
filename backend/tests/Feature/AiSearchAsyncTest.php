<?php

namespace Tests\Feature;

use App\Models\AiSearchRequest;
use App\Models\Plan;
use App\Models\User;
use App\Jobs\InterpretSearchPromptJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiSearchAsyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_user_with_plan_can_queue_ai_search()
    {
        Queue::fake();

        $plan = Plan::factory()->plus()->create();
        $user = User::factory()->create(['plan_id' => $plan->id, 'ai_questions_count' => 0]);

        $response = $this->actingAs($user)->post(route('questions.ai-search'), [
            'prompt' => 'Questões de matemática do ENEM 2022',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'request_id']);
        $this->assertEquals('queued', $response->json('status'));

        $this->assertDatabaseHas('ai_search_requests', [
            'user_id' => $user->id,
            'prompt' => 'Questões de matemática do ENEM 2022',
            'status' => 'pending'
        ]);

        Queue::assertPushed(InterpretSearchPromptJob::class);
    }

    public function test_user_exceeding_quota_cannot_queue_search()
    {
        $plan = Plan::factory()->basic()->create(['max_ai_questions' => 5]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'ai_questions_count' => 5]);

        $response = $this->actingAs($user)->post(route('questions.ai-search'), [
            'prompt' => 'Should fail',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'code' => 'quota_exceeded'
        ]);
    }

    public function test_user_can_check_search_status()
    {
        $user = User::factory()->create();
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user->id,
            'prompt' => 'Test prompt',
            'status' => 'completed',
            'filters' => ['subject' => 'Matemática']
        ]);

        $response = $this->actingAs($user)->get(route('questions.ai-search.status', $searchRequest));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'completed',
            'filters' => ['subject' => 'Matemática']
        ]);
    }

    public function test_user_cannot_check_others_search_status()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $searchRequest = AiSearchRequest::create([
            'user_id' => $user1->id,
            'prompt' => 'Test prompt',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($user2)->get(route('questions.ai-search.status', $searchRequest));

        $response->assertStatus(403);
    }
}
