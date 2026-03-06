<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = \Illuminate\Support\Facades\DB::table('subjects')
        ->leftJoin('question_subject', 'subjects.id', '=', 'question_subject.subject_id')
        ->select('subjects.id', 'subjects.name', \Illuminate\Support\Facades\DB::raw('count(question_subject.question_id) as count'))
        ->where('subjects.name', 'like', '%portug%')
        ->orWhere('subjects.name', 'like', '%língua%')
        ->groupBy('subjects.id', 'subjects.name')
        ->get();

    echo "Subject Counts:\n";
    foreach ($results as $r) {
        echo "ID: {$r->id} | Name: {$r->name} | Questions: {$r->count}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
