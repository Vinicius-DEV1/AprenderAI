<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratuito',
                'slug' => 'free',
                'price' => 0.00,
                'interval' => 'month',
                'simulations_limit' => 5,
                'essays_limit' => 0,
                'features' => [
                    'basic_correction',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Básico',
                'slug' => 'basic',
                'price' => 20.00,
                'interval' => 'month',
                'simulations_limit' => 10,
                'essays_limit' => 2,
                'features' => [
                    'detailed_correction',
                    'improvement_points',
                    'essay_correction',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Plus',
                'slug' => 'plus',
                'price' => 49.90,
                'interval' => 'month',
                'simulations_limit' => 0, // Ilimitado
                'essays_limit' => 20,
                'features' => [
                    'advanced_correction',
                    'personalized_study_plan',
                    'error_explanation',
                    'unlimited_simulations',
                    'essay_examples',
                    'performance_analysis',
                    'time_analysis',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(['slug' => $planData['slug']], $planData);
        }
    }
}
