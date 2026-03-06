<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$essay = App\Models\Essay::find(1);
$resource = new App\Http\Resources\EssayResource($essay);
$resourceArray = $resource->toArray(request());
$fb = $resourceArray['feedback_json'];
echo "Type: " . gettype($fb) . "\n";
if (is_array($fb)) {
    echo "Has corrections? " . (isset($fb['corrections']) ? "YES" : "NO") . "\n";
    echo "Corrections type: " . gettype($fb['corrections'] ?? null) . "\n";
} else {
    echo "String value prefix: " . substr($fb, 0, 100) . "\n";
}
