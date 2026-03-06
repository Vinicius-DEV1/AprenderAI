<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = \Illuminate\Support\Facades\DB::table('subjects')
        ->leftJoin('question_subject', 'subjects.id', '=', 'question_subject.subject_id')
        ->select('subjects.id', 'subjects.name', \Illuminate\Support\Facades\DB::raw('count(question_subject.question_id) as count'))
        ->where('subjects.name', 'like', '%esp%')
        ->groupBy('subjects.id', 'subjects.name')
        ->get();

    echo "Subject Counts for 'Espanhol':\n";
    foreach ($results as $r) {
        echo "ID: {$r->id} | Name: {$r->name} | Questions: {$r->count}\n";
    }

    if ($results->isEmpty()) {
        echo "No subjects found matching '%esp%'.\n";

        $all = \App\Models\Subject::limit(20)->get(['id', 'name']);
        echo "\nSample subjects in DB:\n";
        foreach ($all as $s) {
            echo "ID: {$s->id} | Name: {$s->name}\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
