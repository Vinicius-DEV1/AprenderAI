<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "--- TABLES ---\n";
try {
    $tables = $app->make('db')->select('SHOW TABLES');

    foreach ($tables as $table) {
        echo current((array) $table) . "\n";
    }
} catch (\Exception $e) {
    echo "Error listing tables: " . $e->getMessage() . "\n";
}

echo "\n--- WRITING RULE MODEL ---\n";
echo "Class App\Models\WritingRule exists: " . (class_exists('App\Models\WritingRule') ? 'YES' : "NO\n");

echo "\n--- ESSAYS TABLE STRUCTURE ---\n";
try {
    $schema = $app->make('db')->getSchemaBuilder();
    if ($schema->hasTable('essays')) {
        $columns = $schema->getColumnListing('essays');

        echo implode(', ', $columns) . "\n";
    } else {
        echo "Table 'essays' NOT FOUND\n";
    }
} catch (\Exception $e) {
    echo "Error checking essays table: " . $e->getMessage() . "\n";
}

echo "\n--- LAST ERROR LOGS (Tail) ---\n";
$logPath = dirname(__DIR__) . '/storage/logs/laravel.log';
if (file_exists($logPath)) {
    $lines = explode("\n", file_get_contents($logPath));
    $last20 = array_slice($lines, -20);
    echo implode("\n", $last20) . "\n";
} else {
    echo "Log file not found\n";
}