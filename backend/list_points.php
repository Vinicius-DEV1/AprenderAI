<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\QdrantService;
use Illuminate\Support\Facades\Http;

$host = config('xavier.qdrant.host');
$port = config('xavier.qdrant.port');

$response = Http::post("http://{$host}:{$port}/collections/questions_vectors/points/scroll", [
    'limit' => 5,
    'with_payload' => true
]);

print_r($response->json());
