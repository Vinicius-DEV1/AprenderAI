<?php
// diag_discursive.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use App\Models\QuestionTriageLog;

// Find discursive questions
$discursiveQuestions = Question::where(function($q) {
    $q->where('type', 'discursive')
      ->orWhere('tipo_questao', 'redacao')
      ->orWhere('format', 'redacao');
})->limit(20)->get();

echo "ID\tType\tFormat\tTipoQ\tStatus\tTriageLogsCount\n";
foreach ($discursiveQuestions as $q) {
    $logsCount = QuestionTriageLog::where('question_id', $q->id)->count();
    echo "{$q->id}\t{$q->type}\t{$q->format}\t{$q->tipo_questao}\t{$q->review_status}\t{$logsCount}\n";
    
    if ($logsCount > 0) {
        $log = QuestionTriageLog::where('question_id', $q->id)->latest()->first();
        echo "  - Log Status: {$log->status}\n";
        echo "  - Issues: " . json_encode($log->issues_detected) . "\n";
        echo "  - Quality: {$log->quality_score}\n";
    }
    echo "---------------------------------\n";
}
