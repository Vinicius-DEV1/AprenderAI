<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $apiKeyModel = \App\Models\ApiKey::where('provider', 'gemini')->first();
    $key = $apiKeyModel->decrypted_key;

    $response = Illuminate\Support\Facades\Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$key}");

    if ($response->successful()) {
        $data = $response->json();
        foreach ($data['models'] as $m) {
            if (str_contains($m['name'], 'embed')) {
                echo $m['name'] . " - " . implode(", ", $m['supportedGenerationMethods']) . "\n";
            }
        }
    } else {
        echo "Erro: " . $response->body();
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
