<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

$questions = Question::whereHas('triageLogs', function($q) {
    $q->where('issues', 'like', '%no_alternatives%');
})->with('triageLogs')->limit(10)->get();

echo "ID\tType\tFormat\tTipoQuest\tIssues\n";
foreach ($questions as $q) {
    $issues = json_encode($q->triageLogs->first()->issues);
    echo "{$q->id}\t{$q->type}\t{$q->format}\t{$q->tipo_questao}\t{$issues}\n";
}
