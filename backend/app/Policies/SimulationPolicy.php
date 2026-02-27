<?php

namespace App\Policies;

use App\Models\Simulation;
use App\Models\User;

class SimulationPolicy
{
    public function view(User $user, Simulation $simulation): bool
    {
        return $user->id === $simulation->user_id;
    }

    public function update(User $user, Simulation $simulation): bool
    {
        return $user->id === $simulation->user_id && !$simulation->isFinished();
    }
}
