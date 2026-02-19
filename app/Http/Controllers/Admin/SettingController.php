<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => 'required|string|max:255',
        ]);

        Setting::updateOrCreate(
        ['key' => 'site_name'],
        ['value' => $request->site_name]
        );

        Cache::forget('site_name');

        return redirect()->back()->with('success', 'Configurações atualizadas com sucesso!');
    }
}
