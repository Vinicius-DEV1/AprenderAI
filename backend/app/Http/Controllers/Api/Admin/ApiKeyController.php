<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiKeyVault;
use App\Models\ApiLog;
use App\Models\AiRequestLog;
use App\Models\ApiKeyCapability;
use App\Services\AI\KeyValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    protected $validationService;

    public function __construct(KeyValidationService $validationService)
    {
        $this->validationService = $validationService;
    }
    /**
     * Get API keys monitoring and logs.
     */
    public function index()
    {
        $vaultKeys = ApiKeyVault::orderBy('nickname')->get();

        $availableCapabilities = ApiKey::getAvailableCapabilities();
        $capabilitiesGrid = [];

        foreach ($availableCapabilities as $cap => $label) {
            $capabilitiesGrid[$cap] = ApiKey::whereHas('capabilitiesList', function ($q) use ($cap) {
                $q->where('capability', $cap);
            })
                ->with([
                    'vault',
                    'capabilitiesList' => function ($q) use ($cap) {
                        $q->where('capability', $cap);
                    }
                ])
                ->get()
                ->sortBy(function ($key) {
                    return $key->capabilitiesList->first()->priority ?? 999;
                })
                ->values()
                ->map(function ($key) use ($cap) {
                    // Formato esperado pelo frontend (pivot)
                    $capInfo = $key->capabilitiesList->first();
                    $key->pivot = [
                        'id' => $capInfo?->id ?? 0,
                        'capability' => $capInfo?->capability ?? $cap,
                        'priority' => $capInfo?->priority ?? 0
                    ];
                    return $key;
                });
        }

        $logs = ApiLog::with('apiKey')->latest()->take(20)->get();
        $aiLogs = AiRequestLog::with('user')->latest()->take(20)->get();

        $aiRanking = Cache::remember('ai_consumption_ranking', 3600, function () {
            return AiRequestLog::query()
                ->selectRaw('user_id, SUM(tokens_used_total) as total_tokens, SUM(estimated_cost) as total_cost, COUNT(*) as request_count')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderByDesc('total_tokens')
                ->with('user')
                ->limit(20)
                ->get();
        });

        return response()->json([
            'vault_keys' => $vaultKeys->map(function ($vk) {
                $vk->is_valid = (bool) $vk->is_valid;
                return $vk;
            }),
            'capabilities_grid' => (object) collect($capabilitiesGrid)->map(function ($keys) {
                return $keys->map(function ($key) {
                    $key->is_active = (bool) $key->is_active;
                    $key->capabilities = $key->capabilities ?? [];
                    if ($key->vault) {
                        $key->vault->is_valid = (bool) $key->vault->is_valid;
                    }
                    return $key;
                });
            })->toArray(),
            'available_capabilities' => $availableCapabilities ?: (object) [],
            'logs' => $logs->map(function ($l) {
                $l->type = $l->type ?? 'info';
                return $l;
            }),
            'ai_logs' => $aiLogs,
            'ai_ranking' => $aiRanking,
            'has_recent_errors' => (bool) $logs->where('type', 'error')->where('created_at', '>=', now()->subHours(6))->isNotEmpty()
        ]);
    }

    /**
     * Store a new vault key.
     */
    public function storeVault(Request $request)
    {
        $validated = $request->validate([
            'nickname' => 'required|string|unique:api_key_vaults,nickname',
            'provider' => 'required|in:openai,gemini,grok',
            'key' => 'required|string',
        ]);

        $vault = ApiKeyVault::create($validated + ['is_valid' => true]);

        return response()->json([
            'message' => 'Chave no cofre criada!',
            'vault' => $vault
        ]);
    }

    /**
     * Store/Update routing for a model.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vault_id' => 'required|exists:api_key_vaults,id',
            'preferred_model' => 'required|string',
            'capabilities' => 'required|array',
        ]);

        $vault = ApiKeyVault::findOrFail($validated['vault_id']);

        DB::transaction(function () use ($validated, $vault) {
            // Encontrar ou criar a ApiKey para este par Vault/Modelo
            $apiKey = ApiKey::updateOrCreate([
                'vault_id' => $vault->id,
                'preferred_model' => $validated['preferred_model'],
            ], [
                'provider' => $vault->provider,
                'key' => 'VAULT_REFERENCE', // Valor fictício pois a chave real está no Vault
                'status' => 'online',
                'is_active' => true,
            ]);

            foreach ($validated['capabilities'] as $cap) {
                // Pegar a maior prioridade atual para esta capacidade
                $maxPriority = ApiKeyCapability::where('capability', $cap)->max('priority') ?? 0;

                ApiKeyCapability::firstOrCreate([
                    'api_key_id' => $apiKey->id,
                    'capability' => $cap,
                ], [
                    'priority' => $maxPriority + 1
                ]);
            }

            // Limpar cache de roteamento
            Cache::forget('active_api_keys');
        });

        return response()->json(['message' => 'Roteamento ativado com sucesso!']);
    }

    /**
     * Discover models for a vault key.
     */
    public function discoverModels(Request $request)
    {
        try {
            $request->validate(['vault_id' => 'required|exists:api_key_vaults,id']);
            $vault = ApiKeyVault::findOrFail($request->vault_id);

            $result = $this->validationService->validateKey($vault->provider, $vault->decrypted_key);

            if ($result['is_valid']) {
                $vault->update(['is_valid' => true, 'last_tested_at' => now()]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            file_put_contents('/tmp/error.log', $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'is_valid' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Update priority for a capability.
     */
    public function updatePriority(Request $request)
    {
        $request->validate([
            'capability' => 'required|string',
            'ordered_ids' => 'required|array', // IDs da tabela api_key_capabilities
        ]);

        foreach ($request->ordered_ids as $index => $id) {
            ApiKeyCapability::where('id', $id)->update(['priority' => $index + 1]);
        }

        return response()->json(['message' => 'Prioridades atualizadas!']);
    }

    /**
     * Retest a key.
     */
    public function retest(ApiKey $apiKey)
    {
        $vault = $apiKey->vault;
        if (!$vault) {
            return response()->json(['error' => 'Key sem cofre não pode ser testada individualmente.'], 400);
        }

        $result = $this->validationService->validateKey($vault->provider, $vault->decrypted_key);

        $apiKey->update([
            'status' => $result['is_valid'] ? 'online' : 'offline',
            'last_health_check_at' => now(),
            'last_error_message' => $result['error'] ?? null
        ]);

        if ($result['is_valid']) {
            ApiKey::clearBlacklist($apiKey->id);
        }

        return response()->json($result);
    }

    /**
     * Remove a routing (capability).
     */
    public function destroy($id)
    {
        // Aqui o $id é o ID da tabela api_key_capabilities (o pivot no front)
        ApiKeyCapability::findOrFail($id)->delete();
        return response()->json(['message' => 'Roteamento removido!']);
    }

    /**
     * Toggle active status.
     */
    public function toggle(ApiKey $apiKey)
    {
        $newActive = !$apiKey->is_active;
        $update = ['is_active' => $newActive];

        // Se ativando, força o status para online para limpar erros antigos
        if ($newActive) {
            $update['status'] = 'online';
            ApiKey::clearBlacklist($apiKey->id);
        }

        $apiKey->update($update);

        return response()->json(['message' => 'Status atualizado!', 'is_active' => $apiKey->is_active]);
    }
}
