<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ApiKey;

class DebugGeminiModels extends Command
{
    protected $signature = 'debug:gemini-models';
    protected $description = 'List available Gemini models from Google API';

    public function handle()
    {
        $this->info('Fetching Gemini models...');
        
        $apiKey = ApiKey::getActiveKeyForProvider('gemini');
        
        if (!$apiKey) {
            $this->error('No active Gemini key found.');
            return;
        }

        $key = $apiKey->decrypted_key;
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key={$key}";
        
        $this->info("Using Key: " . substr($key, 0, 5) . '...');
        $this->info("URL: $url");

        try {
            $response = Http::withoutVerifying()->get($url);
            
            if ($response->failed()) {
                $this->error('API Error: ' . $response->status());
                $this->error($response->body());
                return;
            }

            $data = $response->json();
            $models = $data['models'] ?? [];

            $this->info("Found " . count($models) . " models:");
            
            foreach ($models as $model) {
                $this->line(" - {$model['name']}");
            }

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
    }
}
