<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    use HasFactory;

    public const CAPABILITY_QUESTIONS = 'questions';
    public const CAPABILITY_ESSAYS = 'essays';
    public const CAPABILITY_TRIAGE = 'triage';
    public const CAPABILITY_SEARCH = 'search';
    public const CAPABILITY_STUDY_PLANS = 'study_plans';
    public const CAPABILITY_GENERAL = 'general';

    protected $fillable = [
        'vault_id',
        'provider',
        'key',
        'is_active',
        'is_primary',
        'last_used_at',
        'requests_count',
        'preferred_model',
        'capabilities',
        'status',
        'last_health_check_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'last_used_at' => 'datetime',
        'capabilities' => 'array',
    ];

    public static function getAvailableCapabilities(): array
    {
        return [
            self::CAPABILITY_QUESTIONS => 'Geração/Correção de Questões',
            self::CAPABILITY_ESSAYS => 'Avaliação de Redações',
            self::CAPABILITY_TRIAGE => 'Triagem e Moderação',
            self::CAPABILITY_SEARCH => 'Busca Inteligente (Xavier)',
            self::CAPABILITY_STUDY_PLANS => 'Geração de Plano de Estudos',
            self::CAPABILITY_GENERAL => 'Uso Geral / Fallback',
        ];
    }

    public function vault()
    {
        return $this->belongsTo(ApiKeyVault::class, 'vault_id');
    }

    // Dynamic attributes fallback to vault if present
    public function getDecryptedKeyAttribute()
    {
        if ($this->vault_id) {
            return $this->vault->decrypted_key;
        }
        return Crypt::decryptString($this->attributes['key']);
    }

    public function getEffectiveProviderAttribute()
    {
        return $this->vault_id ? $this->vault->provider : $this->provider;
    }

    protected $hidden = [
        'key',
    ];

    public function setKeyAttribute($value)
    {
        $this->attributes['key'] = Crypt::encryptString($value);
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

    /**
     * Motor de Roteamento de API Keys baseado em Capacidades (Resiliência N:N).
     *
     * @param string $capability O uso requerido (ex: 'questions', 'essays')
     * @param string|null $provider Forçar um provider específico, se necessário.
     * @return self|null
     */
    public static function getKeyForCapability(string $capability, ?string $provider = null): ?self
    {
        $query = static::where('is_active', true)->where('status', 'online');
        
        if ($provider) {
            $query->where('provider', $provider);
        }

        // 1. Prioridade Máxima: Chave exata para a Capabillity requerida
        $key = (clone $query)
            ->whereJsonContains('capabilities', $capability)
            ->orderBy('is_primary', 'desc')
            ->orderBy('last_used_at', 'asc')
            ->first();

        // 2. Fallback Inteligente: Tentar uma chave de Uso Geral ('general')
        if (!$key && $capability !== self::CAPABILITY_GENERAL) {
            $key = (clone $query)
                ->whereJsonContains('capabilities', self::CAPABILITY_GENERAL)
                ->orderBy('is_primary', 'desc')
                ->orderBy('last_used_at', 'asc')
                ->first();
        }

        // 3. Fallback Burro Absoluto: Pegar qualquer chave viva para evitar crash da feature
        if (!$key) {
             $key = (clone $query)
                ->orderBy('is_primary', 'desc')
                ->orderBy('last_used_at', 'asc')
                ->first();
        }

        return $key;
    }
}
