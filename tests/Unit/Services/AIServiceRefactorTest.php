<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AIService;
use App\Services\PromptService;
use App\Services\AI\ResponseSanitizer;
use App\Services\AI\AITelemetryService;
use App\Models\ApiKey;
use Mockery;
use ReflectionClass;

class AIServiceRefactorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_aiservice_constructor_injection()
    {
        // This test verifies that AIService accepts the new dependencies.
        // If AIService uses the old constructor (CostCalculator), this will fail or require CostCalculator.

        $promptService = Mockery::mock(PromptService::class);
        $sanitizer = Mockery::mock(ResponseSanitizer::class);
        $telemetry = Mockery::mock(AITelemetryService::class);

        // Attempt to instantiate with NEW signature
        try {
            $service = new AIService($promptService, $sanitizer, $telemetry);
            $this->assertInstanceOf(AIService::class , $service);
        }
        catch (\ArgumentCountError $e) {
            $this->fail('AIService constructor does not match new signature (ArgumentCountError).');
        }
        catch (\TypeError $e) {
            $this->fail('AIService constructor type mismatch: ' . $e->getMessage());
        }
    }

    public function test_dependencies_are_injected_to_properties()
    {
        $promptService = Mockery::mock(PromptService::class);
        $sanitizer = Mockery::mock(ResponseSanitizer::class);
        $telemetry = Mockery::mock(AITelemetryService::class);

        $service = new AIService($promptService, $sanitizer, $telemetry);

        $reflector = new ReflectionClass($service);

        $propSanitizer = $reflector->getProperty('responseSanitizer');
        $propSanitizer->setAccessible(true);
        $this->assertSame($sanitizer, $propSanitizer->getValue($service));

        $propTelemetry = $reflector->getProperty('telemetryService');
        $propTelemetry->setAccessible(true);
        $this->assertSame($telemetry, $propTelemetry->getValue($service));
    }
}
