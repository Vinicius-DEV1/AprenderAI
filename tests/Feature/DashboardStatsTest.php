<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Simulation;
use App\Models\SimulationAnswer;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Essay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock specific services if needed, but integration test is better
        // Ensure plan is unlimited or sufficient
    }

    public function test_dashboard_displays_correct_stats()
    {
        $user = User::factory()->withPlusPlan()->create();

        // 1. Create Subjects
        $math = Subject::factory()->create(['name' => 'Matemática']);
        $port = Subject::factory()->create(['name' => 'Português']);

        // 2. Create Questions
        $qMath = Question::factory()->count(10)->create();
        $qMath->each(fn($q) => $q->subjects()->attach($math));

        $qPort = Question::factory()->count(10)->create();
        $qPort->each(fn($q) => $q->subjects()->attach($port));

        // 3. Create Simulations
        // Sim 1: 5 Math Correct, 5 Math Incorrect (50% Math)
        $sim1 = Simulation::factory()->for($user)->create([
            'status' => 'finished',
            'score' => 500,
        ]);
        $sim1->created_at = now()->subHour();
        $sim1->save();

        foreach ($qMath as $index => $q) {
            SimulationAnswer::create([
                'simulation_id' => $sim1->id,
                'question_id' => $q->id,
                'is_correct' => $index < 5, // 5 correct
                'answer' => 'A'
            ]);
        }

        $sim2 = Simulation::factory()->for($user)->create([
            'status' => 'finished',
        ]);
        $sim2->created_at = now();
        $sim2->save();

        foreach ($qPort as $index => $q) {
            SimulationAnswer::create([
                'simulation_id' => $sim2->id,
                'question_id' => $q->id,
                'is_correct' => $index < 8, // 8 correct
                'answer' => 'B'
            ]);
        }

        // 4. Create Essays
        Essay::factory()->count(3)->for($user)->create(['submitted_at' => now()]);

        // Act
        $response = $this->actingAs($user)->get(route('dashboard'));

        // DEBUG



        // Assert
        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');

        // Check View Data
        $response->assertViewHas('totalSimulations', 2);
        $response->assertViewHas('totalEssays', 3);

        // Math: 5/10 = 50%
        // Port: 8/10 = 80%
        $response->assertViewHas('avgMath', 50.0);
        $response->assertViewHas('avgPortuguese', 80.0);

        // Check Recent Simulations
        $recent = $response->viewData('recentSimulations');
        $this->assertCount(2, $recent);
        $this->assertEquals(80.0, $recent->first()->calculated_score); // Most recent is Sim 2 (Port)

        // Check Subject Performance
        $perf = $response->viewData('subjectPerformance');
        // Might be mixed order, so check collection content
        $mathPerf = $perf->firstWhere('name', 'Matemática');
        $portPerf = $perf->firstWhere('name', 'Português');

        $this->assertEquals(50.0, $mathPerf['percentage']);
        $this->assertEquals(80.0, $portPerf['percentage']);
    }

    public function test_dashboard_handles_empty_data()
    {
        $user = User::factory()->withPlusPlan()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('totalSimulations', 0);
        $response->assertViewHas('avgMath', 0.0);
        $response->assertViewHas('avgPortuguese', 0.0);
        $response->assertViewHas('recentSimulations');
        $this->assertCount(0, $response->viewData('recentSimulations'));
    }
}
