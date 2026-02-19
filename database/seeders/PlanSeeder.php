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
                'features' => [
                    'basic_correction',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Básico',
                'slug' => 'basic',
                'price' => 25.00, // Preço mensal
                'monthly_price' => 25.00,
                'annual_price' => 240.00, // 25*12 = 300, -20% = 240
                'discount_percentage' => 0,
                'interval' => 'monthly',
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
                'price' => 49.90, // Voltando ao valor original
                'monthly_price' => 49.90,
                'annual_price' => 479.00,
                'discount_percentage' => 0,
                'interval' => 'monthly',
                'simulations_limit' => 0, // Ilimitado
                'essays_limit' => 15,
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
                'price' => 240.00, // Total cobrado no ato (anual)
                'monthly_price' => 25.00, // Referência mensal sem desconto
                'annual_price' => 240.00, // Total cobrado
                'discount_percentage' => 20,
                'interval' => 'yearly',
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
                'name' => 'Plus Anual',
                'slug' => 'plus-annual',
                'price' => 479.00, // Voltando ao valor original
                'monthly_price' => 49.90,
                'annual_price' => 479.00,
                'discount_percentage' => 20,
                'interval' => 'yearly',
                'simulations_limit' => 0,
                'essays_limit' => 15,
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
