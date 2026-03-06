<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $subjects = \App\Models\Subject::where('name', 'like', '%portug%')
        ->orWhere('name', 'like', '%língua%')
        ->get(['id', 'name']);

    echo "Subjects found:\n";
    foreach ($subjects as $s) {
        echo "ID: {$s->id} | Name: {$s->name}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
