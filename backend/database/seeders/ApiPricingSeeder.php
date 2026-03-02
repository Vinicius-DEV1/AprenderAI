<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApiPricing;

class ApiPricingSeeder extends Seeder
{
    /**
     * Populates api_pricing with the values previously hardcoded
     * in App\Services\AI\PriceCalculatorService::PRICING_TABLE.
     * Prices are in USD per 1,000,000 tokens.
     */
    public function run(): void
    {
        $models = [
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemini-1.5-flash',
                'input_price_per_1m' => 0.075,
                'output_price_per_1m' => 0.30,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemini-1.5-pro',
                'input_price_per_1m' => 1.25,
                'output_price_per_1m' => 5.00,
            ],
            [
                'api_name' => 'OpenAI',
                'model_key' => 'gpt-4o',
                'input_price_per_1m' => 2.50,
                'output_price_per_1m' => 10.00,
            ],
            [
                'api_name' => 'OpenAI',
                'model_key' => 'gpt-4o-mini',
                'input_price_per_1m' => 0.150,
                'output_price_per_1m' => 0.60,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemma-3-4b-it',
                'input_price_per_1m' => 0.03,
                'output_price_per_1m' => 0.12,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemma-3-12b-it',
                'input_price_per_1m' => 0.07,
                'output_price_per_1m' => 0.30,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemma-3-27b-it',
                'input_price_per_1m' => 0.27,
                'output_price_per_1m' => 1.08,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemini-2.0-flash',
                'input_price_per_1m' => 0.10,
                'output_price_per_1m' => 0.40,
            ],
            [
                'api_name' => 'Google Gemini',
                'model_key' => 'gemini-2.0-flash-lite-preview-02-05',
                'input_price_per_1m' => 0.075,
                'output_price_per_1m' => 0.30,
            ],
            [
                'api_name' => 'xAI',
                'model_key' => 'grok-2',
                'input_price_per_1m' => 2.00,
                'output_price_per_1m' => 10.00,
            ],
            [
                'api_name' => 'Genérico',
                'model_key' => 'default',
                'input_price_per_1m' => 0.00,
                'output_price_per_1m' => 0.00,
            ],
        ];

        foreach ($models as $model) {
            ApiPricing::firstOrCreate(
                ['model_key' => $model['model_key']],
                [
                    'api_name' => $model['api_name'],
                    'input_price_per_1m' => $model['input_price_per_1m'],
                    'output_price_per_1m' => $model['output_price_per_1m'],
                ]
            );
        }
    }
}
