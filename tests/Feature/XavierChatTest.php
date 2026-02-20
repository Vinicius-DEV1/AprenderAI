<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Models\Simulation;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class XavierChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_user_can_send_message_in_simulation_chat()
    {
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();
        $simulation = Simulation::factory()->for($user)->create();
        $question = Question::factory()->create();

        $simulation->answers()->create([
            'question_id' => $question->id,
            'user_answer' => null
        ]);

        $response = $this->actingAs($user)->post(route('simulations.questions.chat.store', [$simulation, $question]), [
            'message' => 'Me explique essa questão',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('question_interactions', [
            'user_id' => $user->id,
            'question_id' => $question->id,
            'simulation_id' => $simulation->id,
            'message' => 'Me explique essa questão',
            'role' => 'user'
        ]);
    }

    public function test_user_can_send_message_in_standalone_chat()
    {
        $user = User::factory()->withPlusPlan()->create();
        $question = Question::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)->post(route('questions.chat.store', [$question]), [
            'message' => 'Dúvida pontual',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('question_interactions', [
            'user_id' => $user->id,
            'question_id' => $question->id,
            'simulation_id' => null,
            'message' => 'Dúvida pontual',
        ]);
    }

    public function test_chat_enforces_ai_quota()
    {
        $user = User::factory()->withFreePlan()->create([
            'ai_questions_count' => 10,
        ]);

        // Ensure plan limit is LOWER than usage
        $user->plan->update(['max_ai_questions' => 5]);
        $user->refresh();
        $user->load('plan');

        $question = Question::factory()->create();

        $response = $this->actingAs($user)->post(route('questions.chat.store', [$question]), [
            'message' => 'Should fail',
        ]);

        // Controller returns 200 with JSON status "quota_exceeded"
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'quota_exceeded',
            'message' => 'Você atingiu o limite de dúvidas do seu plano.'
        ]);
    }

    public function test_chat_history_is_retrievable()
    {
        $user = User::factory()->withPlusPlan()->create();
        $question = Question::factory()->create();
        $simulation = Simulation::factory()->for($user)->create();

        QuestionInteraction::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'simulation_id' => $simulation->id,
            'message' => 'Olá',
            'role' => 'user'
        ]);

        $response = $this->actingAs($user)->get(route('simulations.questions.chat.index', [$simulation, $question]));

        $response->assertStatus(200);
    }
}
