<?php

namespace App\Services;

use App\Models\Question;
use App\Services\AI\AIService;
use App\Services\PromptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionGeneratorService
{
    protected AIService $aiService;
    protected PromptService $promptService;

    public function __construct(AIService $aiService, PromptService $promptService)
    {
        $this->aiService = $aiService;
        $this->promptService = $promptService;
    }

    public function isAiReady(): bool
    {
        return $this->aiService->hasActiveKey(\App\Models\ApiKey::CAPABILITY_QUESTIONS);
    }

    public function generate(string $banca, bool $includeEssay): array
    {
        $questions = [];

        // Batch 1: Português (30 questions)
        $ptQuestions = $this->generateBatch($banca, 'português', 30);
        $questions = array_merge($questions, $ptQuestions);

        // Batch 2: Matemática (30 questions)
        $matQuestions = $this->generateBatch($banca, 'matemática', 30);
        $questions = array_merge($questions, $matQuestions);

        // Save to Database
        $savedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($questions as $qData) {
                // Ensure correct structure and values
                $qData['type'] = 'concurso';
                $qData['source'] = 'ai_generated';

                // Extrai as alternativas que vieram do array para tratar no relacionamento
                $altsData = $qData['alternatives'] ?? [];
                unset($qData['alternatives']);

                // Extrai o subject
                $subjectName = reset($qData['subject']) ?: ($qData['subject'] ?? 'Geral');
                unset($qData['subject']);

                // Extrai gabarito
                $correctAnswer = $qData['correct_answer'] ?? 'A';
                unset($qData['correct_answer']);

                // Duplication check
                $exists = Question::where('type', 'concurso')
                    ->where('statement', $qData['statement'])
                    ->where('source', 'ai_generated')
                    ->exists();

                if (!$exists) {
                    $createdQ = Question::create($qData);

                    if (!empty($altsData) && is_array($altsData)) {
                        foreach ($altsData as $label => $content) {
                            $createdQ->alternatives()->create([
                                'label' => strtoupper($label),
                                'content' => $content,
                                'is_correct' => strtoupper($label) === strtoupper($correctAnswer),
                            ]);
                        }
                    }

                    $subjectModel = \App\Models\Subject::firstOrCreate(
                    ['name' => $subjectName],
                    ['slug' => \Illuminate\Support\Str::slug($subjectName), 'type' => 'concurso']
                    );
                    $createdQ->subjects()->attach($subjectModel->id);

                    $savedCount++;
                }
            }
            DB::commit();
        }
        catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save generated questions: " . $e->getMessage());
            throw $e;
        }

        // Generate Essay if requested
        $essayData = null;
        if ($includeEssay) {
            $essayData = $this->generateEssay($banca);
        }

        return [
            'total_generated' => count($questions),
            'total_inserted' => $savedCount,
            'banca' => $banca,
            'essay_generated' => $includeEssay,
            'essay' => $essayData
        ];
    }

    protected function generateBatch(string $banca, string $subject, int $count): array
    {
        $prompt = $this->buildPrompt($banca, $subject, $count);

        // Call AI Service
        $result = $this->aiService->generateJson($prompt);

        // Parse result
        if (empty($result) || !isset($result['data'])) {
            Log::warning("AI returned empty result for batch $subject");
            return [];
        }

        $data = $result['data'];

        if (isset($data['questions'])) {
            $data = $data['questions'];
        }

        if (!is_array($data)) {
            return [];
        }

        // Filter valid questions
        $validQuestions = [];
        foreach ($data as $item) {
            if ($this->isValidQuestion($item)) {
                $item['subject'] = $subject;
                $validQuestions[] = $item;
            }
        }

        return $validQuestions;
    }

    protected function generateEssay(string $banca): ?array
    {
        $prompt = $this->buildEssayPrompt($banca);
        $result = $this->aiService->generateJson($prompt);

        if (!empty($result) && isset($result['data'])) {
            $data = $result['data'];

            // Persist to essays table as requested
            try {
                $user = \App\Models\User::first();
                if ($user) {
                    \App\Models\Essay::create([
                        'user_id' => $user->id,
                        'title' => $data['title'] ?? 'Redação Inédita - ' . $banca,
                        'theme' => 'banca:' . $banca,
                        'content' => ($data['motivational_text'] ?? '') . "\n\n" . ($data['instructions'] ?? ''),
                        'status' => 'pending', // Default status in migration
                    ]);
                }
            }
            catch (\Exception $e) {
                Log::error("Failed to save essay: " . $e->getMessage());
            }

            return $data;
        }
        return null;
    }

    protected function isValidQuestion($item): bool
    {
        // Strict schema validation as requested
        return is_array($item) &&
            !empty($item['statement']) &&
            !empty($item['alternatives']) && is_array($item['alternatives']) &&
            !empty($item['correct_answer']) &&
            isset($item['year']) &&
            !empty($item['difficulty']) &&
            !empty($item['explanation']);
    }

    protected function buildPrompt(string $banca, string $subject, int $count): string
    {
        // Construct the subject line for the prompt
        $subjectLine = $subject === 'português'
            ? "Matemática: 0\nPortuguês: $count"
            : "Matemática: $count\nPortuguês: 0";

        return $this->promptService->get('question_batch_generator', [
            'banca' => $banca,
            'subject' => $subject,
            'count' => $count,
            'subject_line' => $subjectLine
        ]);
    }

    protected function buildEssayPrompt(string $banca): string
    {
        return $this->promptService->get('essay_batch_generator', [
            'banca' => $banca
        ]);
    }
}
