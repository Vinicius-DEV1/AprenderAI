<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class IntegrationController extends Controller
{
    public function index()
    {
        return view('admin.integrations', [
            'google_login_enabled' => Configuration::get('google_login_enabled', false),
            'google_client_id' => Configuration::get('google_client_id'),
            // We don't send the secret to the view for security, or we could if needed but masked
            // For now let's just show if it's set or not, or maybe just leave it blank to not expose it
            'google_redirect_uri' => Configuration::get('google_redirect_uri', route('auth.google.callback')),

            'analytics_enabled' => Configuration::get('analytics_enabled', false),
            'analytics_measurement_id' => Configuration::get('analytics_measurement_id'),
            'analytics_property_id' => Configuration::get('analytics_property_id'),
            'analytics_sync_frequency' => Configuration::get('analytics_sync_frequency', 'daily'),
            'has_service_account' => Configuration::get('analytics_service_account_json') ? true : false,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'google_login_enabled' => 'boolean',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'google_redirect_uri' => 'nullable|url',

            'analytics_enabled' => 'boolean',
            'analytics_measurement_id' => 'nullable|string|starts_with:G-',
            'analytics_property_id' => 'nullable|numeric',
            'analytics_sync_frequency' => 'nullable|in:daily,hourly',
            'analytics_service_account_json' => 'nullable|file|mimetypes:application/json,text/plain|max:2048',
        ]);

        Configuration::set('google_login_enabled', $request->has('google_login_enabled'));

        if ($request->filled('google_client_id')) {
            Configuration::set('google_client_id', $request->google_client_id);
        }

        if ($request->filled('google_client_secret')) {
            // Encrypt the secret
            Configuration::set('google_client_secret', Crypt::encryptString($request->google_client_secret));
        }

        if ($request->filled('google_redirect_uri')) {
            Configuration::set('google_redirect_uri', $request->google_redirect_uri);
        }

        Configuration::set('analytics_enabled', $request->has('analytics_enabled'));

        if ($request->filled('analytics_measurement_id')) {
            Configuration::set('analytics_measurement_id', $request->analytics_measurement_id);
        }

        if ($request->filled('analytics_property_id')) {
            Configuration::set('analytics_property_id', $request->analytics_property_id);
        }

        if ($request->filled('analytics_sync_frequency')) {
            Configuration::set('analytics_sync_frequency', $request->analytics_sync_frequency);
        }

        if ($request->hasFile('analytics_service_account_json') && $request->file('analytics_service_account_json')->isValid()) {
            $jsonContent = file_get_contents($request->file('analytics_service_account_json')->getRealPath());

            // Validate if it is a valid JSON
            json_decode($jsonContent);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Encrypt before storing in DB
                Configuration::set('analytics_service_account_json', Crypt::encryptString($jsonContent));
            } else {
                return back()->with('error', 'O arquivo enviado não é um JSON válido.');
            }
        }

        return back()->with('success', 'Configurações de integração atualizadas!');
    }
}
