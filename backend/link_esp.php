<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $subjectId = 14; // ESPANHOL
    $questionIds = [834, 1268];

    foreach ($questionIds as $qid) {
        $q = \App\Models\Question::find($qid);
        if ($q) {
            $q->subjects()->syncWithoutDetaching([$subjectId]);
            echo "Linked question {$qid} to Subject {$subjectId}.\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
