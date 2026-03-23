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
    public const CAPABILITY_EMBEDDING = 'embedding';          // Vetores de indexação (batch — IndexQuestionVectorJob)
    public const CAPABILITY_QUERY_EMBEDDING = 'query_embedding'; // Vetores de busca (search — GenerateQueryEmbeddingJob)

    protected $fillable = [
        'vault_id',
        'provider',
        'key',
        'is_active',
        'is_primary',
        'last_used_at',
        'requests_count',
        'preferred_model',
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
    ];

    public static function getAvailableCapabilities(): array
    {
        return [
            self::CAPABILITY_QUESTIONS => 'Geração/Correção de Questões',
            self::CAPABILITY_CHAT_TUTOR => 'Tutor Xavier (Chat de Dúvidas)',
            self::CAPABILITY_ESSAYS => 'Avaliação de Redações',
            self::CAPABILITY_TRIAGE => 'Triagem e Moderação',
            self::CAPABILITY_SEARCH => 'Busca Inteligente (Xavier)',
            self::CAPABILITY_EMBEDDING       => 'Gerador de Vetores (Indexação Batch)',
            self::CAPABILITY_QUERY_EMBEDDING  => 'Embedding de Busca (Xavier Search — Query)',
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
     * Motor de Roteamento M:N com Round-Robin via Redis.
     *
     * Retorna uma Collection ordenada de chaves para o Failover Loop.
     * A GRANDE MELHORIA aqui é o offset de rotação:
     *   - Um contador global por Capability é incrementado atomicamente no Redis.
     *   - O valor do contador (mod total de chaves) define qual chave é a "primeira" da lista.
     *   - Resultado: Workers concorrentes (ex: 4 workers de embeddings) sempre iniciam
     *     com chaves diferentes, distribuindo a carga e evitando que todos "atropelem"
     *     a chave de prioridade máxima ao mesmo tempo.
     *
     * Exemplo com 4 chaves e 4 workers simultâneos:
     *   Worker 1 → contador=1 → inicia na Chave[1]
     *   Worker 2 → contador=2 → inicia na Chave[2]
     *   Worker 3 → contador=3 → inicia na Chave[3]
     *   Worker 4 → contador=4 → inicia na Chave[0] (volta ao início, 4 % 4 = 0)
     *
     * A blacklist global (Redis) garante que, se uma chave queimar (429), todos os
     * workers são avisados imediatamente e pulam a chave problemática.
     */
    public static function getKeysForCapability(string $capability, ?string $provider = null): Collection
    {
        // Lê a lista negra de chaves temporariamente banidas (erros 429, etc.)
        $cacheKeys = \Illuminate\Support\Facades\Cache::get('api_key_blacklist', []);

        $query = static::where('is_active', true)
            ->where('status', 'online')
            ->whereNotIn('id', $cacheKeys); // Filtra chaves na blacklist temporária

        if ($provider) {
            $query->where('provider', $provider);
        }

        // --- Arquitetura Exclusiva M:N (api_key_capabilities) ---
        // Aqui realizamos a busca das chaves rigorosamente pela tabela pivot ApiKeyCapability.
        // Todo o código de suporte ao legado (coluna 'capabilities' JSON da api_keys) foi removido
        $keys = (clone $query)
            ->whereHas('capabilitiesList', function ($sq) use ($capability) {
                // Filtramos a consulta principal para trazer apenas as chaves cujo vínculo
                // específico com a `$capability` requisitada exista.
                $sq->where('capability', $capability);
            })
            ->with([
                // Carregamos a relação para obtermos a prioridade definida pelo administrador
                'capabilitiesList' => function ($q) use ($capability) {
                    $q->where('capability', $capability);
                }
            ])
            ->get()
            ->sortBy(function ($key) {
                // Chaves de menor número em 'priority' têm precedência maior no consumo
                return $key->capabilitiesList->first()->priority ?? 999;
            })
            ->values();

        // -------------------------------------------------------------------
        // ROUND-ROBIN: DISABLED per User Request
        // We always use strict sequential order (priority-based).
        // -------------------------------------------------------------------

        return $keys;
    }

    /**
     * Clears the blacklist and circuit breakers.
     */
    public static function clearBlacklist(?int $id = null): void
    {
        if ($id) {
            $bannedIds = \Illuminate\Support\Facades\Cache::get('api_key_blacklist', []);
            $bannedIds = array_filter($bannedIds, fn($val) => $val != $id);
            \Illuminate\Support\Facades\Cache::put('api_key_blacklist', array_values($bannedIds), now()->addMinutes(60));
        } else {
            \Illuminate\Support\Facades\Cache::forget('api_key_blacklist');
            
            // Clear circuit breakers for all capabilities to allow jobs to resume immediately
            $capabilities = array_keys(self::getAvailableCapabilities());
            foreach ($capabilities as $cap) {
                \Illuminate\Support\Facades\Cache::forget("ai_circuit_breaker_{$cap}");
            }
        }
    }
}
