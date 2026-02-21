<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Blade::component('layouts.app', 'layouts.app');
        \Illuminate\Support\Facades\Blade::component('layouts.admin', 'layouts.admin');

        // Compartilhar configurações globais
        view()->composer('*', function ($view) {
            $siteSettings = \Illuminate\Support\Facades\Cache::remember('site_settings', 3600, function () {
                return \App\Models\Setting::whereIn('key', ['site_name', 'ai_name'])->pluck('value', 'key');
            });

            $siteName = $siteSettings['site_name'] ?? config('app.name');
            $aiName = $siteSettings['ai_name'] ?? 'Xavier';

            // Update config dynamically for emails and other components
            config(['app.name' => $siteName]);

            $view->with([
                'siteName' => $siteName,
                'aiName' => $aiName
            ]);
        });
    }
}
