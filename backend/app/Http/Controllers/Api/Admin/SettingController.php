<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Get system settings.
     */
    public function index()
    {
        return response()->json([
            'cache_stats' => [
                'driver' => config('cache.default'),
                // Stats could be added here if Redis/Memcached is used
            ],
            'app_version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
        ]);
    }

    /**
     * Clear system cache.
     */
    public function clearCache(Request $request)
    {
        $type = $request->get('type', 'all');

        switch ($type) {
            case 'view':
                Artisan::call('view:clear');
                break;
            case 'route':
                Artisan::call('route:clear');
                break;
            case 'config':
                Artisan::call('config:clear');
                break;
            default:
                Artisan::call('cache:clear');
                break;
        }

        return response()->json(['message' => "Cache ($type) limpo com sucesso!"]);
    }
}
