<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'key',
        'is_active',
        'is_primary',
        'last_used_at',
        'requests_count',
        'preferred_model',
        'status',
        'last_health_check_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    public function setKeyAttribute($value)
    {
        $this->attributes['key'] = Crypt::encryptString($value);
    }

    public function getDecryptedKeyAttribute()
    {
        return Crypt::decryptString($this->attributes['key']);
    }

    public function incrementUsage(): void
    {
        $this->increment('requests_count');
        $this->update(['last_used_at' => now()]);
    }

    public static function getActiveKeyForProvider(string $provider): ?self
    {
        return static::where('provider', $provider)
            ->where('is_active', true)
            ->where('status', 'online') // Garantir que só pegamos chaves funcionais
            ->orderBy('is_primary', 'desc')
            ->orderBy('last_used_at', 'asc') // Rodízio: pega a que não é usada há mais tempo
            ->first();
    }
}
