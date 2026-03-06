<?php
$logPath = __DIR__ . '/storage/logs/laravel.log';
if (!file_exists($logPath)) {
    echo "Log file not found at: $logPath\n";
    exit(1);
}
// Read the last 1000 lines
$lines = file($logPath);
$lines = array_slice($lines, -1000);
$foundError = false;
foreach ($lines as $line) {
    if (strpos($line, 'Next Doctrine\DBAL\Driver\PDO\Exception') !== false || strpos($line, 'SQLSTATE') !== false || strpos($line, 'QueryException') !== false) {
        echo $line;
    }
}
