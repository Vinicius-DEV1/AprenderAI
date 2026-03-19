<?php

namespace App\Services\Study;

use App\Models\User;
use App\Models\UserTopicStat;
use Illuminate\Support\Facades\DB;

class StudyStatsService
{
    // Subject display names and targets
    protected array $subjectMeta = [
        'Matemática' => ['target' => 65, 'label' => 'Matemática'],
        'Matematica' => ['target' => 65, 'label' => 'Matemática'],
        'Língua Portuguesa' => ['target' => 70, 'label' => 'Língua Portuguesa'],
        'Portugues' => ['target' => 70, 'label' => 'Língua Portuguesa'],
        'Português' => ['target' => 70, 'label' => 'Língua Portuguesa'],
        'Ciências da Natureza' => ['target' => 60, 'label' => 'Natureza'],
        'Natureza' => ['target' => 60, 'label' => 'Natureza'],
        'Ciências Humanas' => ['target' => 65, 'label' => 'Humanas'],
        'Humanas' => ['target' => 65, 'label' => 'Humanas'],
        'Linguagens' => ['target' => 70, 'label' => 'Linguagens'],
        'Redação' => ['target' => 900, 'label' => 'Redação'],
    ];

    public function resolveSubjectMeta(string $subject): array
    {
        $normalizedInput = $this->normalizeSubjectName($subject);

        foreach ($this->subjectMeta as $key => $meta) {
            $normalizedKey = $this->normalizeSubjectName($key);
            if ($normalizedInput === $normalizedKey) {
                return $meta;
            }
        }

        return ['target' => 65, 'label' => $subject];
    }

    public function normalizeSubjectName(string $subject): string
    {
        $subject = mb_strtolower(trim($subject), 'UTF-8');
        $subject = preg_replace('/[áàâãä]/u', 'a', $subject);
        $subject = preg_replace('/[éèêë]/u', 'e', $subject);
        $subject = preg_replace('/[íìîï]/u', 'i', $subject);
        $subject = preg_replace('/[óòôõö]/u', 'o', $subject);
        $subject = preg_replace('/[úùûü]/u', 'u', $subject);
        $subject = preg_replace('/[ç]/u', 'c', $subject);

        // Map common variations
        if (str_contains($subject, 'matematica'))
            return 'matematica';
        if (str_contains($subject, 'portugues') || str_contains($subject, 'linguagem'))
            return 'portugues';
        if (str_contains($subject, 'natureza'))
            return 'natureza';
        if (str_contains($subject, 'humana'))
            return 'humanas';
        if (str_contains($subject, 'redaca'))
            return 'redacao';

        return $subject;
    }

    public function updateUserStats(User $user, $simulation)
    {
        foreach ($simulation->answers as $answer) {
            $question = $answer->question;
            // N:N Logic (Pivot): The relational structure of Subjects and Topics requires extracting the first element 
            // through relationships. This extraction aims to maintain compatibility with the string-based metrics engine
            // and falls back to 'General' if the N:N queries return null.
            $subjectName = $question->subjects->first()?->name ?? 'Geral';
            $topicName = $question->topics->first()?->name ?? 'Geral';

            $stat = UserTopicStat::firstOrNew([
                'user_id' => $user->id,
                'subject' => $subjectName,
                'topic' => $topicName,
            ]);

            $stat->attempts++;
            if ($answer->is_correct) {
                $stat->correct++;
            }
            $stat->accuracy = ($stat->correct / $stat->attempts) * 100;
            $stat->last_attempt_at = now();
            $stat->save();
        }

        // Xavier 2.0: Synchronize weak and strong themes in the UserStat model for faster re-ranking
        $this->syncUserThemes($user);
    }

    /**
     * Synchronizes the top 5 weak and strong topics into the UserStat model.
     * This provides a flattened cache for the ReRankService to apply proficiency boosts.
     */
    protected function syncUserThemes(User $user): void
    {
        $stats = UserTopicStat::where('user_id', $user->id)->get();
        if ($stats->isEmpty()) return;

        // Weakest: Low accuracy topics with at least 2 attempts to avoid noise from early fails
        $weak = $stats->where('attempts', '>=', 2)
            ->sortBy('accuracy')
            ->take(5)
            ->map(fn($s) => $s->topic)
            ->values()
            ->toArray();

        // Strongest: High accuracy topics
        $strong = $stats->where('attempts', '>=', 2)
            ->sortByDesc('accuracy')
            ->take(5)
            ->map(fn($s) => $s->topic)
            ->values()
            ->toArray();

        \App\Models\UserStat::updateOrCreate(
            ['user_id' => $user->id],
            [
                'weak_themes' => $weak,
                'strong_themes' => $strong,
                'updated_at' => now()
            ]
        );
    }

    public function buildStats(User $user): array
    {
        $stats = UserTopicStat::where('user_id', $user->id)->get();

        $aggregated = [];
        foreach ($stats as $s) {
            $norm = $this->normalizeSubjectName($s->subject);
            if (!isset($aggregated[$norm])) {
                $aggregated[$norm] = [
                    'attempts' => 0,
                    'correct' => 0,
                ];
            }
            $aggregated[$norm]['attempts'] += $s->attempts;
            $aggregated[$norm]['correct'] += $s->correct;
        }

        $bySubject = collect($aggregated)->map(function ($data) {
            $total = $data['attempts'];
            $correct = $data['correct'];
            return [
                'attempts' => $total,
                'correct' => $correct,
                'accuracy' => $total >= 2 ? round(($correct / $total) * 100, 2) : null,
            ];
        });

        $topWeak = $stats->sortBy('accuracy')->take(5)->map(fn($s) => [
            'subject' => $this->resolveSubjectMeta($this->normalizeSubjectName($s->subject))['label'],
            'topic' => $s->topic,
            'accuracy' => $s->accuracy,
        ])->values();

        $topStrong = $stats->sortByDesc('accuracy')->take(5)->map(fn($s) => [
            'subject' => $this->resolveSubjectMeta($this->normalizeSubjectName($s->subject))['label'],
            'topic' => $s->topic,
            'accuracy' => $s->accuracy,
        ])->values();

        $trend = $this->calculateTrend($user);

        return [
            'subjects' => $bySubject,
            'weak_topics' => $topWeak,
            'strong_topics' => $topStrong,
            'trend' => $trend,
        ];
    }

    public function calculateTrend(User $user): array
    {
        $sims = $user->simulations()
            ->where('status', 'finished')
            ->latest()
            ->take(5)
            ->get()
            ->reverse();

        if ($sims->count() < 2) {
            return ['status' => 'estável', 'data' => []];
        }

        $scores = $sims->map(fn($s) => $s->score)->values()->toArray();
        $first = $scores[0];
        $last = end($scores);

        if ($last > $first + 50)
            return ['status' => 'melhorando'];
        if ($last < $first - 50)
            return ['status' => 'piorando'];
        return ['status' => 'estável'];
    }

    public function getDataConfidence(User $user): array
    {
        $total = (int) UserTopicStat::where('user_id', $user->id)->sum('attempts');

        if ($total >= 100) {
            $level = 'high';
            $label = 'Alta confiança estatística';
        } elseif ($total >= 50) {
            $level = 'medium';
            $label = 'Confiança estatística moderada';
        } else {
            $level = 'low';
            $label = 'Baixa confiança estatística';
        }

        return [
            'level' => $level,
            'label' => $label,
            'total' => $total,
            'warning' => $level === 'low'
                ? 'Análise com baixa confiança estatística. Complete ao menos 100 questões para maior precisão.'
                : null,
        ];
    }

    public function recentAccuracy(User $user, int $days): ?float
    {
        $stats = UserTopicStat::where('user_id', $user->id)
            ->where('last_attempt_at', '>=', now()->subDays($days))
            ->get();

        if ($stats->isEmpty())
            return null;

        $total = $stats->sum('attempts');
        $correct = $stats->sum('correct');

        return $total > 0 ? round(($correct / $total) * 100, 1) : null;
    }
}
