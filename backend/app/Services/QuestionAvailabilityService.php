<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Simulation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestionAvailabilityService
{
    /**
     * Get IDs of questions seen in the last 10 finished simulations.
     */
    public function getLastSeenQuestionIds(User $user, int $limit = 10): array
    {
        $lastSimulations = Simulation::where('user_id', $user->id)
            ->where('status', 'finished')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');

        if ($lastSimulations->isEmpty()) {
            return [];
        }

        return DB::table('simulation_answers')
            ->whereIn('simulation_id', $lastSimulations)
            ->pluck('question_id')
            ->toArray();
    }

    /**
     * Get available questions for a specific subject, applying recurrence filter.
     * Returns separate collections for Real and IA questions.
     */
    public function getAvailableQuestions(User $user, string $subject, int $neededCount): array
    {
        $ignoredIds = $this->getLastSeenQuestionIds($user);

        // Fetch Real Questions (up to the full needed amount to allow fallback if AI is missing)
        // Fetch Real Questions (up to the full needed amount to allow fallback if AI is missing)
        $realQuestions = Question::whereHas('subjects', function ($q) use ($subject) {
            $q->where('name', $subject);
        })
            ->where(function ($q) {
            $q->where('source', 'enem_real_2009_2023')
                ->orWhere('source', 'manual');
        })
            ->whereNotIn('id', $ignoredIds)
            ->inRandomOrder()
            ->limit($neededCount)
            ->get();

        // Fetch Existing AI Questions (up to the full needed amount to allow fallback if Real is missing)
        // Fetch Existing AI Questions (up to the full needed amount to allow fallback if Real is missing)
        $aiQuestions = Question::whereHas('subjects', function ($q) use ($subject) {
            $q->where('name', $subject);
        })
            ->where('source', 'ai_generated')
            ->whereNotIn('id', $ignoredIds)
            ->inRandomOrder()
            ->limit($neededCount)
            ->get();

        return [
            'real' => $realQuestions,
            'ai' => $aiQuestions,
            'ignored_ids' => $ignoredIds
        ];
    }
}
