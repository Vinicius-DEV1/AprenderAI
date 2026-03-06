<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\Admin\QuestionController;
use Illuminate\Http\Request;

echo "--- Simulating AdminQuestionController@index ---" . PHP_EOL;

$controller = new QuestionController();
$request = Request::create('/api/v1/admin/questions', 'GET');

$response = $controller->index($request);
$data = json_decode($response->getContent(), true);

$questions = $data['questions']['data'] ?? [];
$total = $data['questions']['total'] ?? 0;

echo "Total questions returned in 'questions': " . $total . PHP_EOL;
echo "Count of questions in first page: " . count($questions) . PHP_EOL;

$missingSubjectCount = 0;
foreach ($questions as $q) {
    if (empty($q['subjects'])) {
        $missingSubjectCount++;
        echo "FAIL: Question #{$q['id']} has NO subjects in the response!" . PHP_EOL;
    }
}

echo "Total questions with missing subjects in response: " . $missingSubjectCount . PHP_EOL;

if ($total > 0 && $questions) {
    echo "Sample Question #{$questions[0]['id']} subjects: " . count($questions[0]['subjects']) . PHP_EOL;
}
