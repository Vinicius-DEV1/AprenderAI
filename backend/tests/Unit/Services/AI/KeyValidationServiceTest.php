<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\KeyValidationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeyValidationServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KeyValidationService();
    }

    public function test_validate_key_returns_error_for_unknown_provider()
    {
        $result = $this->service->validateKey('unknown', 'key');
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('não suportado', $result['error']);
    }

    public function test_validate_openai_key_success()
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4o']]], 200)
        ]);

        $result = $this->service->validateKey('openai', 'so-secret');
        $this->assertTrue($result['is_valid']);
        $this->assertNotEmpty($result['models']);
    }

    public function test_validate_gemini_key_success()
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['models' => [['name' => 'models/gemini-pro']]], 200)
        ]);

        $result = $this->service->validateKey('gemini', 'so-secret');
        $this->assertTrue($result['is_valid']);
        $this->assertNotEmpty($result['models']);
    }

    public function test_validate_key_handles_api_failure()
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response(['error' => ['message' => 'Invalid key']], 401)
        ]);

        $result = $this->service->validateKey('openai', 'bad-key');
        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Invalid key', $result['error']);
    }
}
