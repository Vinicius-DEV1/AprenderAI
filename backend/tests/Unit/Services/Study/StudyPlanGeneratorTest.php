<?php

namespace Tests\Unit\Services\Study;

use Tests\TestCase;
use App\Models\User;
use App\Models\StudyPlan;
use App\Models\Plan;
use App\Models\Simulation;
use App\Services\Study\StudyPlanGenerator;
use App\Services\Study\StudyStatsService;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class StudyPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected $generator;
    protected $aiServiceMock;
    protected $statsServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiServiceMock = Mockery::mock(AIService::class);
        $this->statsServiceMock = Mockery::mock(StudyStatsService::class);

        $this->generator = new StudyPlanGenerator(
            $this->aiServiceMock,
            $this->statsServiceMock
        );
    }

    protected function setupUserWithAccess()
    {
        $planModel = Plan::factory()->create(['name' => 'Plus']);
        $user = User::factory()->create(['plan_id' => $planModel->id]);

        $question = \App\Models\Question::factory()->create();

        $sim = Simulation::factory()->create([
            'user_id' => $user->id,
            'status' => 'finished'
        ]);
        $sim->answers()->create([
            'question_id' => $question->id,
            'user_answer' => 'A',
            'is_correct' => true
        ]);

        return $user;
    }

    /** @test */
    public function it_denies_generation_if_prerequisites_not_met()
    {
        $user = User::factory()->create(); // No plan, no sim

        $this->assertFalse($this->generator->canGenerate($user));
    }

    /** @test */
    public function it_allows_generation_for_qualified_user_without_plan()
    {
        $user = $this->setupUserWithAccess();

        $this->assertTrue($this->generator->canGenerate($user));
    }

    /** @test */
    public function it_denies_generation_if_recent_plan_exists()
    {
        $user = $this->setupUserWithAccess();
        StudyPlan::create([
            'user_id' => $user->id,
            'exam_type' => 'enem',
            'status' => 'ready',
            'hours_per_day' => 4,
            'created_at' => now()->subDays(5) // Less than 30 days
        ]);

        $this->assertFalse($this->generator->canGenerate($user));
    }

    /** @test */
    public function it_creates_placeholder_plan()
    {
        $user = $this->setupUserWithAccess();
        $input = [
            'exam_type' => 'enem',
            'hours_per_day' => 3
        ];

        $plan = $this->generator->createPlaceholder($user, $input);

        $this->assertInstanceOf(StudyPlan::class, $plan);
        $this->assertEquals('processing', $plan->status);
        $this->assertEquals(3, $plan->hours_per_day);
        $this->assertDatabaseHas('study_plans', ['id' => $plan->id]);
    }
}
