<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $subjects = \Illuminate\Support\Facades\DB::table('subjects')->pluck('name')->toArray();
    echo "Materias cadastradas: \n";
    print_r($subjects);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
