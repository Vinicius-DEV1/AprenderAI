<?php

namespace App\Providers;

use App\Models\Simulation;
use App\Models\Essay;
use App\Policies\SimulationPolicy;
use App\Policies\EssayPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Simulation::class => SimulationPolicy::class,
        Essay::class => EssayPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
