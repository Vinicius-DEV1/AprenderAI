<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AI\AITelemetryService;

class AITelemetryServiceTest extends TestCase
{
    /**
     * Test if token estimation works correctly with characters-to-tokens ratio.
     */
    public function test_estimate_tokens()
    {
        $service = new AITelemetryService();

        $this->assertEquals(0, $service->estimateTokens(''), 'Empty string should yield 0 tokens');

        // "Hello" is 5 chars. 5 / 3.8 = 1.31 -> ceil is 2
        $this->assertEquals(2, $service->estimateTokens('Hello'));

        // Long string test
        $text = str_repeat('a', 380); // 380 chars
        $this->assertEquals(100, $service->estimateTokens($text), '380 chars should be approx 100 tokens');
    }
}
