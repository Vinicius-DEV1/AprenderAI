<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '90323245';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS aprovaai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database 'aprovaai' created successfully.\n";
} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}
