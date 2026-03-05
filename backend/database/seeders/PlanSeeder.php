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
                'monthly_price' => 0.00,
                'annual_price' => 0.00,
                'discount_percentage' => 0,
                'interval' => 'monthly',
                'simulations_limit' => 5,
                'essays_limit' => 0,
                'daily_question_limit' => 30,
                'features' => [
                    'basic_correction',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Básico',
                'slug' => 'basic',
                'price' => 25.00,
                'monthly_price' => 25.00,
                'annual_price' => 240.00,
                'discount_percentage' => 0,
                'interval' => 'monthly',
                'simulations_limit' => 10,
                'essays_limit' => 5,
                'daily_question_limit' => 30,
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
                'monthly_price' => 49.90,
                'annual_price' => 480.00,
                'discount_percentage' => 0,
                'interval' => 'monthly',
                'simulations_limit' => 0,
                'essays_limit' => 15,
                'daily_question_limit' => 9999,
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
            [
                'name' => 'Básico Anual',
                'slug' => 'basic-annual',
                'price' => 240.00,
                'monthly_price' => 25.00,
                'annual_price' => 240.00,
                'discount_percentage' => 20,
                'interval' => 'yearly',
                'simulations_limit' => 10,
                'essays_limit' => 5,
                'daily_question_limit' => 30,
                'features' => [
                    'detailed_correction',
                    'improvement_points',
                    'essay_correction',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Plus Anual',
                'slug' => 'plus-annual',
                'price' => 480.00,
                'monthly_price' => 40.00,
                'annual_price' => 480.00,
                'discount_percentage' => 20,
                'interval' => 'yearly',
                'simulations_limit' => 0,
                'essays_limit' => 15,
                'daily_question_limit' => 9999,
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

        \Illuminate\Support\Facades\Cache::forget('active_plans');
        $this->command->info('Planos atualizados e cache "active_plans" limpo com sucesso.');
    }
}
