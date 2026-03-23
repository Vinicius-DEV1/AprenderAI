<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Campaign;

try {
    // Create a test campaign
    $campaign = Campaign::create([
        'name' => 'Teste de Verificação',
        'slug' => 'teste-' . time(),
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'venda_quente',
        'base_url' => '/register'
    ]);

    echo "Campanha criada ID: " . $campaign->id . "\n";
    echo "Tracking URL: " . $campaign->tracking_url . "\n";

    if (str_contains($campaign->tracking_url, 'utm_source=google') && str_contains($campaign->tracking_url, '/register')) {
        echo "VERIFICAÇÃO SUCESSO: URL de rastreamento correta.\n";
    } else {
        echo "VERIFICAÇÃO FALHA: URL de rastreamento incorreta.\n";
    }

    // Cleanup
    $campaign->delete();
    echo "Campanha de teste removida.\n";

} catch (\Exception $e) {
    echo "ERRO NA VERIFICAÇÃO: " . $e->getMessage() . "\n";
}
