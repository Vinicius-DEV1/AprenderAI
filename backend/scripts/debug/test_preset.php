<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = \Illuminate\Http\Request::create('/api/v1/admin/simulations/presets', 'POST', [
    'name' => 'PresetTeste2',
    'type' => 'enem',
    'is_active' => true,
    'rules' => [
        [
            'category' => 'subject_distribution',
            'configuration' => ['ai_ratio' => 0.1, 'human_ratio' => 0.9]
        ]
    ]
]);

try {
    $user = \App\Models\User::where('role', 'admin')->first();
    if ($user) {
        \Illuminate\Support\Facades\Auth::login($user);
    }
    $response = app()->handle($request);
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
