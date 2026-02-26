<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiKeyVault;
use App\Models\ApiLog;
use App\Models\AiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiKeyController extends Controller
{
    /**
     * Get API keys monitoring and logs.
     */
    public function index()
    {
        $vaultKeys = ApiKeyVault::orderBy('nickname')->get();

        $capabilitiesGrid = [];
        $availableCapabilities = ApiKey::getAvailableCapabilities();

        foreach ($availableCapabilities as $cap => $label) {
            $capabilitiesGrid[$cap] = ApiKey::getKeysForCapability($cap);
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
            'vault_keys' => $vaultKeys,
            'capabilities' => $capabilitiesGrid,
            'available_capabilities' => $availableCapabilities,
            'logs' => $logs,
            'ai_logs' => $aiLogs,
            'ai_ranking' => $aiRanking
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
     * Toggle active status.
     */
    public function toggle(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return response()->json(['message' => 'Status atualizado!', 'is_active' => $apiKey->is_active]);
    }
}
