<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

try {
    echo "--- DIAGNOSTICS ---\n";
    echo "Total questions: " . Question::count() . "\n";
    echo "Total with difficulty: " . Question::whereNotNull('difficulty')->count() . "\n";
    echo "Total with difficulty_reasoning: " . Question::whereNotNull('difficulty_reasoning')->count() . "\n";
    echo "Total with explanation: " . Question::whereNotNull('explanation')->count() . "\n";
    echo "Total with subjects: " . Question::has('subjects')->count() . "\n";
    echo "Total with topics: " . Question::has('topics')->count() . "\n";
    
    echo "Total COMPLETE: " . Question::complete()->count() . "\n";
    
    echo "Total is_active: " . Question::where('is_active', true)->count() . "\n";
    echo "Total review_status null: " . Question::whereNull('review_status')->count() . "\n";
    echo "Total review_status approved: " . Question::where('review_status', 'approved')->count() . "\n";
    
    echo "Total PUBLISHED: " . Question::published()->count() . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
