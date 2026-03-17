<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


class PlatformEvent extends Model
{
    use FiltersAdmins;

    protected $fillable = [
        'user_id',
        'session_token',
        'event_type',
        'page',
        'resource_id',
        'resource_type',
        'duration_seconds',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
