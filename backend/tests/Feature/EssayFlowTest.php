<?php

namespace Tests\Feature;

use App\Models\Essay;
use App\Models\User;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Jobs\EvaluateEssayJob;

class EssayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear all previous setup to start fresh for integration testing
        // Need real AI service mock with specific behaviors
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_enem_offtopic_sets_score_zero()
    {
        // Mock AIService specifically for this evaluation
        $mockAiService = $this->mock(AIService::class);
        $mockAiService->shouldReceive('detectOffTopic')
            ->once()
            ->andReturn(['off_topic' => true, 'reason' => 'Falou sobre receita de bolo.']);

        $user = User::factory()->create();
        $essay = Essay::factory()->create([
            'user_id' => $user->id,
            'type' => 'enem',
            'status' => 'evaluating',
            'content' => 'Receita de bolo de cenoura com cobertura de chocolate.',
            'title' => 'Os desafios da saúde pública'
        ]);

        $job = new EvaluateEssayJob($essay);
        $job->handle($mockAiService);

        $essay->refresh();

        $this->assertEquals(0, $essay->score);
        $this->assertEmpty($essay->competencies);
        $this->assertTrue($essay->off_topic);
        $this->assertEquals('Falou sobre receita de bolo.', $essay->off_topic_reason);
        $this->assertTrue($essay->final_score_locked);
        $this->assertEquals('completed', $essay->status);
        $this->assertEquals(0, $essay->feedback_json['score']);
    }

    public function test_concurso_offtopic_sets_score_zero()
    {
        $mockAiService = $this->mock(AIService::class);
        $mockAiService->shouldReceive('detectOffTopic')
            ->once()
            ->andReturn(['off_topic' => true, 'reason' => 'Total fuga do tema Concurso.']);

        $user = User::factory()->create();
        $essay = Essay::factory()->create([
            'user_id' => $user->id,
            'type' => 'concurso',
            'status' => 'evaluating',
            'content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            'title' => 'O papel do Estado na economia'
        ]);

        $job = new EvaluateEssayJob($essay);
        $job->handle($mockAiService);

        $essay->refresh();

        $this->assertEquals(0, $essay->score);
        $this->assertTrue($essay->off_topic);
        $this->assertTrue($essay->final_score_locked);
    }

    public function test_non_offtopic_keeps_normal_flow()
    {
        $mockAiService = $this->mock(AIService::class);

        $mockAiService->shouldReceive('detectOffTopic')
            ->once()
            ->andReturn(['off_topic' => false, 'reason' => 'Dentro do tema']);

        $mockAiService->shouldReceive('evaluateEssay')
            ->once()
            ->andReturn([
                'provider' => 'mock',
                'status' => 'success',
                'raw_response' => '...',
                'response' => [
                    'score' => 840,
                    'summary' => 'Boa redação',
                    'strengths' => [],
                    'weaknesses' => [],
                    'checklist' => [],
                    'corrections' => [],
                    'improved_version' => '...',
                    'competencies' => [
                        ['name' => 'C1', 'score' => 160],
                        ['name' => 'C2', 'score' => 200],
                        ['name' => 'C3', 'score' => 160],
                        ['name' => 'C4', 'score' => 160],
                        ['name' => 'C5', 'score' => 160],
                    ]
                ]
            ]);

        $user = User::factory()->create();
        $essay = Essay::factory()->create([
            'user_id' => $user->id,
            'type' => 'enem',
            'status' => 'evaluating',
            'content' => 'O papel do Estado na economia é fundamental.',
            'title' => 'O papel do Estado na economia'
        ]);

        $job = new EvaluateEssayJob($essay);
        $job->handle($mockAiService);

        $essay->refresh();

        $this->assertEquals(840, $essay->score);
        $this->assertFalse($essay->off_topic);
        $this->assertFalse($essay->final_score_locked);
        $this->assertEquals('completed', $essay->status);
        $this->assertEquals(840, $essay->feedback_json['score']);

        // Assert sum matches
        $sum = array_sum(array_column($essay->feedback_json['competencies'], 'score'));
        $this->assertEquals(840, $sum);
    }

    public function test_image_upload_valid()
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();
        $essay = Essay::factory()->for($user)->create([
            'status' => 'in_progress',
            'type' => 'enem'
        ]);

        // GD extension is missing in this Docker environment
        // we can fake a file upload using create() and passing mime
        $file = UploadedFile::fake()->create('redacao.jpg', 1024, 'image/jpeg');

        $response = $this->actingAs($user)->postJson('/api/v1/essays/' . $essay->id . '/submit', [
            'image' => $file
        ]);

        $response->assertStatus(200);

        $essay->refresh();
        $this->assertEquals('image', $essay->input_type);
        $this->assertEquals('evaluating', $essay->status);
        $this->assertNotNull($essay->image_path);

        Storage::disk('public')->assertExists($essay->image_path);

        Queue::assertPushed(EvaluateEssayJob::class);
    }

    public function test_image_upload_invalid_type()
    {
        Storage::fake('public');
        Queue::fake();

        $user = User::factory()->withPlusPlan()->create();
        $essay = Essay::factory()->for($user)->create([
            'status' => 'in_progress'
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($user)->postJson('/api/v1/essays/' . $essay->id . '/submit', [
            'image' => $file
        ]);

        // Validation error
        $response->assertStatus(422);

        $essay->refresh();
        $this->assertEquals('in_progress', $essay->status); // unchanged

        Queue::assertNotPushed(EvaluateEssayJob::class);
    }
}
