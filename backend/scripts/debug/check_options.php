<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $questionService = app(\App\Services\QuestionService::class);
    $options = $questionService->getFilterOptions();

    // Filtra apenas o que interessa para o teste pra não poluir o output
    $subjects = array_filter($options['subjects'], function ($s) {
        return stripos($s['name'], 'portug') !== false || stripos($s['name'], 'língua') !== false;
    });

    echo "Subjects in options passed to AI:\n";
    echo json_encode(array_values($subjects), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
