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

        // Compartilhar nome do site globalmente
        view()->composer('*', function ($view) {
            $siteName = \Illuminate\Support\Facades\Cache::remember('site_name', 3600, function () {
                    return \App\Models\Setting::where('key', 'site_name')->value('value') ?? config('app.name');
                }
                );

                // Update config dynamically for emails and other components
                config(['app.name' => $siteName]);

                $view->with('siteName', $siteName);
            });
    }
}
