<?php
// diag_simple.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = \App\Models\QuestionTriageLog::where('issues_detected', 'like', '%no_alternatives%')
    ->with('question')
    ->latest()
    ->limit(10)
    ->get();

foreach ($logs as $log) {
    if (!$log->question) continue;
    echo "ID: " . $log->question->id . " | Type: " . $log->question->type . " | Format: " . $log->question->format . " | TipoQuestao: " . $log->question->tipo_questao . "\n";
    echo "Issues: " . json_encode($log->issues_detected) . "\n";
    echo "---------------------------------\n";
}
