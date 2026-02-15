<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'users_count' => \App\Models\User::count(),
            'simulations_count' => \App\Models\Simulation::count(),
            'essays_count' => \App\Models\Essay::count(),
            'api_keys_count' => ApiKey::count(),
            'recent_users' => \App\Models\User::latest()->take(5)->get(),
        ]);
    }

    public function apiKeys()
    {
        $keys = ApiKey::orderBy('provider')->get();
        return view('admin.api-keys', compact('keys'));
    }

    public function storeApiKey(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:openai,gemini,grok',
            'key' => 'required|string',
        ]);

        // Se for a primeira chave deste provider, torna-a primária
        $isPrimary = !ApiKey::where('provider', $request->provider)->where('is_primary', true)->exists();

        ApiKey::create([
            'provider' => $request->provider,
            'key' => $request->key, // Setter encrypts automatically
            'is_active' => true,
            'is_primary' => $isPrimary,
        ]);

        return back()->with('success', 'Chave adicionada com sucesso!');
    }

    public function toggleApiKey(ApiKey $apiKey)
    {
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return back()->with('success', 'Status da chave atualizado!');
    }

    public function destroyApiKey(ApiKey $apiKey)
    {
        $apiKey->delete();

        // Se deletou a primária, promove outra
        if ($apiKey->is_primary) {
            $nextKey = ApiKey::where('provider', $apiKey->provider)->first();
            if ($nextKey) {
                $nextKey->update(['is_primary' => true]);
            }
        }

        return back()->with('success', 'Chave removida!');
    }
}
