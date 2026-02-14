<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "1. Loading autoload...\n";
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Vendor autoload missing!\n");
}
require __DIR__ . '/vendor/autoload.php';
echo "2. Autoload loaded.\n";

echo "3. Creating app...\n";
$app = require_once __DIR__ . '/bootstrap/app.php';
echo "4. App created.\n";

$basePath = $app->basePath();
echo "Base Path: $basePath\n";
$cachePath = $app->bootstrapPath('cache');
echo "Bootstrap cache path: $cachePath\n";

if (file_exists($cachePath)) {
    echo "Cache path exists.\n";
    if (is_dir($cachePath)) {
        echo "Cache path is a directory.\n";
    } else {
        echo "Cache path is NOT a directory!\n";
    }

    if (is_writable($cachePath)) {
        echo "Cache path is writable.\n";
    } else {
        echo "Cache path is NOT writable!\n";
        // Attempt to fix
        if (chmod($cachePath, 0777)) {
            echo "Attempted chmod 0777... Success?\n";
        } else {
            echo "Attempted chmod 0777... Failed.\n";
        }
    }
} else {
    echo "Cache path does NOT exist.\n";
    if (mkdir($cachePath, 0777, true)) {
        echo "Created cache path.\n";
    } else {
        echo "Failed to create cache path.\n";
    }
}

echo "5. Making Kernel...\n";
try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "6. Kernel made.\n";

    echo "7. Bootstrapping...\n";
    $kernel->bootstrap();
    echo "8. Bootstrapped successfully.\n";

    echo "9. Instantiating PackageManifest...\n";
    $files = new \Illuminate\Filesystem\Filesystem();
    $basePath = $app->basePath();
    $manifestPath = $app->bootstrapPath('cache/packages.php');
    $manifest = new \Illuminate\Foundation\PackageManifest($files, $basePath, $manifestPath);

    echo "Manifest path: " . $manifest->manifestPath . "\n";
    echo "Vendor path: " . $manifest->vendorPath . "\n";
    echo "Dirname of manifest: " . dirname($manifest->manifestPath) . "\n";
    echo "Is dirname writable? " . (is_writable(dirname($manifest->manifestPath)) ? 'YES' : 'NO') . "\n";

    echo "10. Attempting to build manifest...\n";
    $manifest->build();
    echo "11. Manifest built successfully!\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
