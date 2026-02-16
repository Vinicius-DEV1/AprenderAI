<?php

use App\Models\Question;
use App\Models\User;
use App\Services\SimulationCreationService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- DATABASE CHECK ---\n";
$sources = DB::table('questions')->select('subject', 'source')->distinct()->get();
foreach ($sources as $s) {
    echo "Subject: {$s->subject} | Source: {$s->source}\n";
}

echo "\n--- COUNT CHECK ---\n";
$matReal = Question::where('type', 'enem')->where('subject', 'matemática')->where('source', 'enem_real_2009_2023')->count();
$portReal = Question::where('type', 'enem')->where('subject', 'português')->where('source', 'enem_real_2009_2023')->count();
echo "Matemática Real: $matReal\n";
echo "Português Real: $portReal\n";

echo "\n--- SIMULATION CREATION TEST ---\n";
$user = User::where('email', 'plus@aprovaai.test')->first();
if (!$user) {
    echo "User not found!\n";
    exit;
}

$service = app(SimulationCreationService::class);
$data = [
    'type' => 'enem',
    'total_questions' => 90,
    'subject_distribution' => [
        'matemática' => 45,
        'português' => 45
    ]
];

try {
    $sim = $service->createSimulation($user, $data);
    echo "Success!\n";
    echo "Simulado ID: {$sim->id}\n";
    echo "Status: {$sim->status}\n";
    echo "Total Answers: " . $sim->answers()->count() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
