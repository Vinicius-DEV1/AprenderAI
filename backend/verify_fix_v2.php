<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\Admin\ExamController;
use Illuminate\Http\Request;

$request = new Request([
    'year' => 2010,
    'organization' => 'ENEM',
    'institution' => 'INEP',
    'role' => 'Estudante'
]);

$controller = new ExamController();
$response = $controller->show($request, 'null');
$data = $response->getData();
echo "Count: " . count($data->data) . "\n";
foreach ($data->data as $q) {
    echo "ID: " . $q->id . " - " . $q->organization . " " . $q->year . "\n";
    break; // Just one
}
