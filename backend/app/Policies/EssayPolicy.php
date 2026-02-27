<?php

namespace App\Policies;

use App\Models\Essay;
use App\Models\User;

class EssayPolicy
{
    public function view(User $user, Essay $essay): bool
    {
        return $user->id === $essay->user_id;
    }

    public function update(User $user, Essay $essay): bool
    {
        return $user->id === $essay->user_id && in_array($essay->status, ['draft', 'in_progress']);
    }
}
