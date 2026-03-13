<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use Illuminate\Support\Facades\Config;

// Force local connection for diagnosis
Config::set('database.connections.mysql.host', '127.0.0.1');
Config::set('database.connections.mysql.username', 'root');
Config::set('database.connections.mysql.password', ''); // Default Laragon

try {
    echo "--- DIAGNOSTICS ---\n";
    echo "Total questions: " . Question::count() . "\n";
    
    // Check complete() sub-filters
    echo "Difficulty NOT NULL: " . Question::whereNotNull('difficulty')->count() . "\n";
    echo "Difficulty NOT EMPTY: " . Question::whereRaw("TRIM(difficulty) != ''")->count() . "\n";
    echo "Difficulty Reasoning NOT NULL: " . Question::whereNotNull('difficulty_reasoning')->count() . "\n";
    echo "Difficulty Reasoning NOT EMPTY: " . Question::whereRaw("TRIM(difficulty_reasoning) != ''")->count() . "\n";
    echo "Explanation NOT NULL: " . Question::whereNotNull('explanation')->count() . "\n";
    echo "Explanation NOT EMPTY: " . Question::whereRaw("TRIM(explanation) != ''")->count() . "\n";
    echo "Has Subjects: " . Question::has('subjects')->count() . "\n";
    echo "Has Topics: " . Question::has('topics')->count() . "\n";
    
    echo "Total COMPLETE: " . Question::complete()->count() . "\n";
    
    echo "Is Active: " . Question::where('is_active', true)->count() . "\n";
    echo "Review Status Approved: " . Question::where('review_status', 'approved')->count() . "\n";
    echo "Review Status NULL: " . Question::whereNull('review_status')->count() . "\n";
    
    echo "Total PUBLISHED: " . Question::published()->count() . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
