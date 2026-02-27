<?php

namespace Tests\Unit\Services\Study;

use App\Services\Study\StudyStatsService;
use PHPUnit\Framework\TestCase;

class StudyStatsServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StudyStatsService();
    }

    /** @test */
    public function it_normalizes_subject_names_correctly()
    {
        $this->assertEquals('matematica', $this->service->normalizeSubjectName('Matemática'));
        $this->assertEquals('matematica', $this->service->normalizeSubjectName('Matematica'));
        $this->assertEquals('portugues', $this->service->normalizeSubjectName('Língua Portuguesa'));
        $this->assertEquals('portugues', $this->service->normalizeSubjectName('Portugues'));
        $this->assertEquals('humanas', $this->service->normalizeSubjectName('Ciências Humanas'));
        $this->assertEquals('redacao', $this->service->normalizeSubjectName('Redação'));
    }

    /** @test */
    public function it_resolves_subject_meta_data()
    {
        $meta = $this->service->resolveSubjectMeta('Matemática');
        $this->assertEquals(65, $meta['target']);
        $this->assertEquals('Matemática', $meta['label']);

        $meta = $this->service->resolveSubjectMeta('Redação');
        $this->assertEquals(900, $meta['target']);
    }

    /** @test */
    public function it_defaults_meta_for_unknown_subjects()
    {
        $meta = $this->service->resolveSubjectMeta('Astronomia');
        $this->assertEquals(65, $meta['target']); // Default fallback
        $this->assertEquals('Astronomia', $meta['label']);
    }
}
