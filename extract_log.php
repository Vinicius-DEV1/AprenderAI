<?php
$logPath = __DIR__ . '/backend/storage/logs/laravel.log';
if (!file_exists($logPath)) {
    echo "Log file not found at: $logPath\n";
    exit(1);
}
// Read the last 1000 lines
$lines = file($logPath);
$lines = array_slice($lines, -1000);
$foundError = false;
foreach ($lines as $line) {
    if (strpos($line, 'QueryException') !== false || strpos($line, 'SQLSTATE') !== false) {
        $foundError = true;
    }
    if ($foundError) {
        echo $line;
    }
}
