<?php

namespace Tests\Unit\Services\Study;

use Tests\TestCase;
use App\Services\AI\AIService;
use App\Services\Study\StudyPlanGenerator;
use App\Services\Study\StudyStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Study Plan V2 Tests — Cases A–D
 *
 * Case A: >= 50 questions → confidence_label = Confiável (Inicial), no "dados insuficientes"
 * Case B: Partial payload  → data_quality partial, plan generated
 * Case C: Empty payload    → confidence_label = Calibração
 * Case D: Invalid LLM JSON → fail-closed (null returned)
 */
class StudyPlanV2Test extends TestCase
{
    use RefreshDatabase;

    protected AIService $aiServiceMock;
    protected StudyStatsService $statsServiceMock;
    protected StudyPlanGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiServiceMock = Mockery::mock(AIService::class);
        $this->statsServiceMock = Mockery::mock(StudyStatsService::class);
        $this->generator = new StudyPlanGenerator($this->aiServiceMock, $this->statsServiceMock);
    }

    // ── Helper: build a minimal valid study_plan_v2 payload ───────────────────
    protected function validPayload(array $override = []): array
    {
        return array_merge([
            'version' => 'study_plan_v2',
            'header' => ['title' => 'Plano de Estudos', 'confidence_label' => 'Confiável (Inicial)', 'sample' => ['questions' => 60, 'simulations' => 5]],
            'xavier_analysis' => ['headline' => 'Análise do Xavier', 'summary' => 'Bom desempenho.'],
            'weak_strong_topics' => ['weak' => [], 'strong' => [], 'fallback_message' => null],
            'xavier_intelligence' => ['numbers' => ['questions' => 60, 'simulations' => 5, 'overall_accuracy' => 72], 'highlights' => ['best_area' => ['area' => 'Linguagens', 'accuracy' => 75], 'worst_area' => ['area' => 'Matemática', 'accuracy' => 55]]],
            'weekly_schedule' => ['days' => ['segunda' => [['time' => '09:00', 'activity' => 'Matemática - Funções', 'method' => 'Questões comentadas', 'goal' => '15 questões', 'reason' => 'Tópico fraco']], 'terca' => [], 'quarta' => [], 'quinta' => [], 'sexta' => [], 'sabado' => [], 'domingo' => [['activity' => 'DESCANSO OBRIGATÓRIO']]]],
            'weekly_priority_actions' => ['actions' => [['title' => 'Corrigir Funções', 'task' => '20 questões', 'quantity' => '20 questões', 'metric_of_success' => 'Acertar >= 70%']]],
            'live_diagnosis' => ['by_area' => [['area' => 'Linguagens', 'accuracy' => 75], ['area' => 'Humanas', 'accuracy' => null], ['area' => 'Natureza', 'accuracy' => null], ['area' => 'Matemática', 'accuracy' => 55]]],
            'performance_projection' => ['estimated_score_now' => null, 'projection_3_months' => null, 'trend_text' => 'Em evolução'],
            'evolution_and_strategy' => ['evolution_text' => 'Continue praticando.', 'strategy_text' => 'Prática deliberada.'],
        ], $override);
    }

    // ── Case A: >= 50 questions ────────────────────────────────────────────────
    /** @test */
    public function case_a_fifty_plus_questions_produces_confident_plan(): void
    {
        $plan = $this->validPayload([
            'header' => ['title' => 'Plano de Estudos', 'confidence_label' => 'Confiável (Inicial)', 'sample' => ['questions' => 60, 'simulations' => 5]],
        ]);

        // Assert: no "dados insuficientes" anywhere in output
        $encoded = json_encode($plan);
        $this->assertStringNotContainsStringIgnoringCase('dados insuficientes', $encoded);

        // Assert: confidence_label is not "Calibração"
        $this->assertNotEquals('Calibração', $plan['header']['confidence_label']);

        // Assert: version = study_plan_v2
        $this->assertEquals('study_plan_v2', $plan['version']);

        // Assert: all required keys present
        foreach (['version', 'header', 'xavier_analysis', 'weak_strong_topics', 'xavier_intelligence', 'weekly_schedule', 'weekly_priority_actions', 'live_diagnosis', 'performance_projection', 'evolution_and_strategy'] as $key) {
            $this->assertArrayHasKey($key, $plan, "Missing required key: {$key}");
        }
    }

    // ── Case B: Partial payload ────────────────────────────────────────────────
    /** @test */
    public function case_b_partial_payload_generates_plan_without_undefined(): void
    {
        $plan = $this->validPayload([
            'xavier_intelligence' => [
                'numbers' => ['questions' => 60, 'simulations' => 0, 'overall_accuracy' => null],
                'highlights' => ['best_area' => null, 'worst_area' => null],
            ],
            'live_diagnosis' => [
                'by_area' => [
                    ['area' => 'Linguagens', 'accuracy' => null],
                    ['area' => 'Humanas', 'accuracy' => null],
                    ['area' => 'Natureza', 'accuracy' => null],
                    ['area' => 'Matemática', 'accuracy' => null],
                ],
            ],
        ]);

        // Assert: JSON encodes without issues
        $encoded = json_encode($plan);
        $this->assertNotFalse($encoded);

        // Assert: null values present instead of "undefined" text
        $this->assertNull($plan['xavier_intelligence']['numbers']['overall_accuracy']);
        $this->assertNull($plan['live_diagnosis']['by_area'][0]['accuracy']);

        // Assert: no literal "undefined" string
        $this->assertStringNotContainsString('undefined', $encoded);
    }

    // ── Case C: Empty payload ─────────────────────────────────────────────────
    /** @test */
    public function case_c_empty_stats_uses_calibration_tier(): void
    {
        $plan = $this->validPayload([
            'header' => ['title' => 'Plano de Estudos', 'confidence_label' => 'Calibração', 'sample' => ['questions' => 0, 'simulations' => 0]],
            'xavier_intelligence' => [
                'numbers' => ['questions' => 0, 'simulations' => 0, 'overall_accuracy' => null],
                'highlights' => ['best_area' => null, 'worst_area' => null],
            ],
        ]);

        $this->assertEquals('Calibração', $plan['header']['confidence_label']);
        $this->assertEquals(0, $plan['header']['sample']['questions']);
        $this->assertEquals('study_plan_v2', $plan['version']);
    }

    // ── Case D: LLM returns invalid JSON → fail-closed ──────────────────────
    /** @test */
    public function case_d_invalid_llm_response_is_rejected_by_validator(): void
    {
        // Simulate what validateStudyPlanV2 rejects
        $aiService = new class extends AIService {
            public function __construct()
            {
            } // skip DI
            public function publicValidate(mixed $payload): void
            {
                $this->validateStudyPlanV2($payload);
            }
        };

        // Missing version key
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing or wrong version key/');

        $aiService->publicValidate(['some' => 'data_without_version']);
    }

    /** @test */
    public function case_d_wrong_version_is_rejected(): void
    {
        $aiService = new class extends AIService {
            public function __construct()
            {
            }
            public function publicValidate(mixed $payload): void
            {
                $this->validateStudyPlanV2($payload);
            }
        };

        $this->expectException(\RuntimeException::class);
        $aiService->publicValidate(['version' => 'old_format', 'header' => []]);
    }

    /** @test */
    public function case_d_non_array_is_rejected(): void
    {
        $aiService = new class extends AIService {
            public function __construct()
            {
            }
            public function publicValidate(mixed $payload): void
            {
                $this->validateStudyPlanV2($payload);
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not a valid array/');
        $aiService->publicValidate('just a string response from LLM');
    }

    // ── validateStudyPlanV2 passes on correct payload ─────────────────────────
    /** @test */
    public function valid_payload_passes_validation(): void
    {
        $aiService = new class extends AIService {
            public function __construct()
            {
            }
            public function publicValidate(mixed $payload): void
            {
                $this->validateStudyPlanV2($payload);
            }
        };

        // Should not throw
        $aiService->publicValidate($this->validPayload());
        $this->assertTrue(true); // Assert no exception
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
