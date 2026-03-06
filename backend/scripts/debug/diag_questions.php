<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;

echo "--- Diagnostic: Complete Questions ---" . PHP_EOL;

$questions = Question::complete()->with('subjects')->get();

$countMissingSubject = 0;
foreach ($questions as $q) {
    if ($q->subjects->isEmpty()) {
        $countMissingSubject++;
        echo "Error: Question #{$q->id} is in 'complete' scope but has NO subjects." . PHP_EOL;
    }
}

echo "Total complete questions: " . $questions->count() . PHP_EOL;
echo "Total with missing subjects found in 'complete' scope: " . $countMissingSubject . PHP_EOL;

echo "--- Diagnostic: Incomplete Questions ---" . PHP_EOL;
$incompleteCount = Question::incomplete()->count();
echo "Total incomplete questions: " . $incompleteCount . PHP_EOL;

$semMateriaCount = Question::whereDoesntHave('subjects')->count();
echo "Total questions WITHOUT any subject: " . $semMateriaCount . PHP_EOL;

$pivotCount = \DB::table('question_subject')->count();
echo "Total rows in question_subject pivot: " . $pivotCount . PHP_EOL;

$orphanedPivots = \DB::table('question_subject')
    ->whereNotExists(function ($query) {
        $query->select(\DB::raw(1))
            ->from('subjects')
            ->whereRaw('subjects.id = question_subject.subject_id');
    })->count();
echo "Orphaned rows in question_subject (pointing to missing subjects): " . $orphanedPivots . PHP_EOL;

$semMateriaInComplete = Question::complete()->whereDoesntHave('subjects')->count();
echo "Total questions WITHOUT subject that are considered COMPLETE: " . $semMateriaInComplete . PHP_EOL;

echo "--- SQL Query ---" . PHP_EOL;
echo Question::complete()->toSql() . PHP_EOL;
