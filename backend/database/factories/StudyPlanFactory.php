<?php

namespace Database\Factories;

use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyPlanFactory extends Factory
{
    protected $model = StudyPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_type' => 'enem',
            'hours_per_day' => 4,
            'plan_json' => [
                'overview' => 'Plano Teste',
                'weekly_schedule' => [],
                'focus_points' => []
            ],
            'stats_snapshot' => [],
            'generated_at' => now(),
            'status' => 'ready',
            'next_generate_at' => now()->addDays(30),
            'next_update_at' => now()->addDays(14),
        ];
    }
}
