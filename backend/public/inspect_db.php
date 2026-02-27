<?php

try {
    // Correct path relative to public/
    $dbPath = __DIR__ . '/../banco_provas_completo.db';

    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found at: $dbPath");
    }

    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to SQLite database at $dbPath\n";

    // Get tables
    echo "Tables:\n";
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
    $tables = [];
    foreach ($result as $row) {
        $tables[] = $row['name'];
        echo "- " . $row['name'] . "\n";
    }

    echo "\nSchema Details:\n";
    foreach ($tables as $table) {
        echo "--------------------------------------------------\n";
        echo "Table: $table\n";

        // Get schema
        $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$table'");
        $schema = $stmt->fetchColumn();
        echo "SQL: $schema\n";

        // Get row count
        $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
        echo "Rows: $count\n";

        // Get a sample row
        echo "Sample Row:\n";
        $stmt = $db->query("SELECT * FROM \"$table\" LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($row);
    }

}
catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
