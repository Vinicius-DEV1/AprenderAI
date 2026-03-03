<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class IntegrationController extends Controller
{
    /**
     * Get integration settings.
     */
    public function index()
    {
        return response()->json([
            'google_login_enabled' => filter_var(Configuration::get('google_login_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'google_client_id' => Configuration::get('google_client_id', ''),
            'google_redirect_uri' => url('/api/v1/auth/google/callback'),

            'analytics_enabled' => filter_var(Configuration::get('analytics_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'analytics_measurement_id' => Configuration::get('analytics_measurement_id', ''),
            'analytics_property_id' => Configuration::get('analytics_property_id', ''),
            'analytics_sync_frequency' => Configuration::get('analytics_sync_frequency', 'daily'),
            'has_service_account' => Storage::disk('local')->exists('google/service-account.json'),
        ]);
    }

    /**
     * Update integration settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_login_enabled' => 'required|in:0,1',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',

            'analytics_enabled' => 'required|in:0,1',
            'analytics_measurement_id' => 'nullable|string',
            'analytics_property_id' => 'nullable|string',
            'analytics_sync_frequency' => 'required|in:daily,hourly',
            'analytics_service_account_json' => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Google Login Settings
        Configuration::set('google_login_enabled', $request->boolean('google_login_enabled'));
        Configuration::set('google_client_id', $request->google_client_id);
        if ($request->filled('google_client_secret')) {
            Configuration::set('google_client_secret', Crypt::encryptString($request->google_client_secret));
        }

        // Ensure redirect URI is saved if not present (Auth controller uses it)
        Configuration::set('google_redirect_uri', url('/api/v1/auth/google/callback'));

        // Analytics Settings
        Configuration::set('analytics_enabled', $request->boolean('analytics_enabled'));
        Configuration::set('analytics_measurement_id', $request->analytics_measurement_id);
        Configuration::set('analytics_property_id', $request->analytics_property_id);
        Configuration::set('analytics_sync_frequency', $request->analytics_sync_frequency);

        // Service Account JSON
        if ($request->hasFile('analytics_service_account_json')) {
            $path = $request->file('analytics_service_account_json')->storeAs(
                'google',
                'service-account.json',
                'local'
            );
            Configuration::set('analytics_service_account_path', $path);
        }

        return response()->json([
            'message' => 'Configurações de integração atualizadas com sucesso!',
            'data' => $this->index()->getData()
        ]);
    }
}
