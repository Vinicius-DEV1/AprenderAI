<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConfigResource;
use App\Models\Configuration;
use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class ConfigController extends Controller
{
    /**
     * GET /api/v1/config
     *
     * Retorna as configurações iniciais do sistema para o React SPA.
     * Dados dinâmicos: branding, features habilitadas, planos ativos.
     */
    public function index()
    {
        $siteSettings = Cache::remember('site_settings', 3600, function () {
            return Setting::whereIn('key', ['site_name', 'ai_name'])->pluck('value', 'key');
        });

        $configurations = Cache::remember('app_configurations', 3600, function () {
            return Configuration::whereIn('key', [
                'google_login_enabled',
                'essays_enabled',
                'simulations_enabled',
                'study_plan_enabled',
                'question_bank_enabled',
                'analytics_enabled',
                'analytics_measurement_id',
            ])->pluck('value', 'key');
        });

        $plans = Cache::remember('active_plans', 3600, function () {
            return Plan::where('is_active', true)->orderBy('price')->get();
        });

        $data = [
            'app_name' => $siteSettings['site_name'] ?? config('app.name'),
            'ai_name' => $siteSettings['ai_name'] ?? 'Xavier',
            'app_version' => config('app.version', '1.0.0'),
            'google_login_enabled' => filter_var(
                $configurations['google_login_enabled'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
            'analytics' => [
                'enabled' => filter_var($configurations['analytics_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'measurement_id' => $configurations['analytics_measurement_id'] ?? null,
            ],
            'features' => [
                'essays' => filter_var($configurations['essays_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'simulations' => filter_var($configurations['simulations_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'study_plan' => filter_var($configurations['study_plan_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'question_bank' => filter_var($configurations['question_bank_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ],
            'plans' => $plans,
        ];

        return new ConfigResource($data);
    }
}
