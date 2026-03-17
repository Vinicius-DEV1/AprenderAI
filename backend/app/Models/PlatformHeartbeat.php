<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\FiltersAdmins;


class PlatformHeartbeat extends Model
{
    use FiltersAdmins;

    protected $fillable = [
        'user_id',
        'session_token',
        'current_page',
        'pinged_at',
    ];

    protected function casts(): array
    {
        return [
            'pinged_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
