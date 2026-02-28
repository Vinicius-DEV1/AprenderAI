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
     * All view composers and Blade component registrations have been removed.
     * Site name / AI name branding is now served exclusively via GET /api/v1/config.
     */
    public function boot(): void
    {
        //
    }
}
