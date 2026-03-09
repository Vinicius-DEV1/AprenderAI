<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Collection;

class ApiKey extends Model
{
    use HasFactory;

    public const CAPABILITY_QUESTIONS = 'questions';
    public const CAPABILITY_ESSAYS = 'essays';
    public const CAPABILITY_TRIAGE = 'triage';
    public const CAPABILITY_SEARCH = 'search';
    public const CAPABILITY_STUDY_PLANS = 'study_plans';
    public const CAPABILITY_CHAT_TUTOR = 'chat_tutor';
    public const CAPABILITY_GENERAL = 'general';
    public const CAPABILITY_EMBEDDING = 'embedding';

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
        'last_error_message',
        'last_error_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'last_used_at' => 'datetime',
        'last_error_at' => 'datetime',
        'capabilities' => 'array',
    ];

    public static function getAvailableCapabilities(): array
    {
        return [
            self::CAPABILITY_QUESTIONS => 'Geração/Correção de Questões',
            self::CAPABILITY_CHAT_TUTOR => 'Tutor Xavier (Chat de Dúvidas)',
            self::CAPABILITY_ESSAYS => 'Avaliação de Redações',
            self::CAPABILITY_TRIAGE => 'Triagem e Moderação',
            self::CAPABILITY_SEARCH => 'Busca Inteligente (Xavier)',
            self::CAPABILITY_STUDY_PLANS => 'Geração de Plano de Estudos',
            self::CAPABILITY_EMBEDDING => 'Gerador de Vetores (Embeddings)',
            self::CAPABILITY_GENERAL => 'Uso Geral / Fallback',
        ];
    }

    public function vault()
    {
        return $this->belongsTo(ApiKeyVault::class, 'vault_id');
    }

    public function capabilitiesList()
    {
        return $this->hasMany(ApiKeyCapability::class)->orderBy('priority');
    }

    // Dynamic attributes fallback to vault if present
    public function getDecryptedKeyAttribute()
    {
        if ($this->vault_id) {
            return $this->vault->decrypted_key;
        }
        return \Illuminate\Support\Facades\Crypt::decryptString($this->attributes['key']);
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
        $this->attributes['key'] = \Illuminate\Support\Facades\Crypt::encryptString($value);
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
     * Retorna UMA ÚNICA CHAVE (Legacy) - será substituída por getKeysForCapability
     */
    public static function getKeyForCapability(string $capability, ?string $provider = null): ?self
    {
        return static::getKeysForCapability($capability, $provider)->first();
    }
    /**
     * Motor de Roteamento M:N.
     * Retorna uma Collection ordenada de chaves disponíveis para o Failover Loop.
     */
    public static function getKeysForCapability(string $capability, ?string $provider = null): Collection
    {
        $cacheKeys = \Illuminate\Support\Facades\Cache::get('api_key_blacklist', []);

        $query = static::where('is_active', true)
            ->where('status', 'online')
            ->whereNotIn('id', $cacheKeys); // Filtra chaves na blacklist temporária

        if ($provider) {
            $query->where('provider', $provider);
        }

        // Tentar buscar as chaves com a nova arquitetura M:N ordenadas pela prioridade
        $keys = (clone $query)
            ->where(function ($q) use ($capability) {
                $q->whereHas('capabilitiesList', function ($sq) use ($capability) {
                    $sq->where('capability', $capability);
                });

                // Fallback: Se for 'general', aceita chaves que NÃO tem capacidades definidas (Retrocompatibilidade)
                if ($capability === self::CAPABILITY_GENERAL) {
                    $q->orWhereDoesntHave('capabilitiesList')
                        ->where(function ($sq) {
                            $sq->whereNull('capabilities')
                                ->orWhere('capabilities', '[]')
                                ->orWhere('capabilities', '');
                        });
                }
            })
            ->with([
                'capabilitiesList' => function ($q) use ($capability) {
                    $q->where('capability', $capability);
                }
            ])
            ->get()
            ->sortBy(function ($key) {
                return $key->capabilitiesList->first()->priority ?? 999;
            })
            ->values();

        if ($keys->isNotEmpty()) {
            return $keys;
        }

        // --- Fallback para arquitetura legada (coluna JSON) até a migração de dados estar completa ---

        $legacyKeys = collect();

        // 1. Prioridade Máxima: Chave exata para a Capabillity requerida
        $key1 = (clone $query)
            ->where(function ($q) use ($capability) {
                $q->whereJsonContains('capabilities', $capability);
                if ($capability === self::CAPABILITY_GENERAL) {
                    $q->orWhereNull('capabilities')
                        ->orWhere('capabilities', '[]')
                        ->orWhere('capabilities', '');
                }
            })
            ->orderBy('is_primary', 'desc')
            ->orderBy('last_used_at', 'asc')
            ->first();

        if ($key1)
            $legacyKeys->push($key1);

        // 2. Fallback Inteligente: Tentar uma chave de Uso Geral ('general')
        if (!$key1 && $capability !== self::CAPABILITY_GENERAL) {
            $key2 = (clone $query)
                ->where(function ($q) {
                    $q->whereJsonContains('capabilities', self::CAPABILITY_GENERAL)
                        ->orWhereNull('capabilities')
                        ->orWhere('capabilities', '[]')
                        ->orWhere('capabilities', '');
                })
                ->orderBy('is_primary', 'desc')
                ->orderBy('last_used_at', 'asc')
                ->first();

            if ($key2)
                $legacyKeys->push($key2);
        }

        // 3. Removido Fallback Absoluto que causava exibição em todas as rotas
        return $legacyKeys;
    }

    /**
     * Limpa o cache de blacklist para esta chave ou globalmente.
     */
    public static function clearBlacklist(?int $id = null): void
    {
        if ($id) {
            $bannedIds = \Illuminate\Support\Facades\Cache::get('api_key_blacklist', []);
            $bannedIds = array_filter($bannedIds, fn($val) => $val != $id);
            \Illuminate\Support\Facades\Cache::put('api_key_blacklist', array_values($bannedIds), now()->addMinutes(60));
        } else {
            \Illuminate\Support\Facades\Cache::forget('api_key_blacklist');
        }
    }
}
