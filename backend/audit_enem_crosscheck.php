<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$sampleImg = DB::table('questions')
    ->join('question_alternatives', 'questions.id', '=', 'question_alternatives.question_id')
    ->where('questions.type', 'enem')
    ->whereNotNull('question_alternatives.image_path')
    ->select('questions.id', 'questions.year', 'questions.statement', 'questions.external_id', 'questions.knowledge_area')
    ->first();

if ($sampleImg) {
    echo "ID:{$sampleImg->id} Year:{$sampleImg->year} KA:{$sampleImg->knowledge_area}\n";
    echo "statement_full:[\n" . ($sampleImg->statement ?? '') . "\n]\n\n";
    $alts = DB::table('question_alternatives')->where('question_id', $sampleImg->id)->orderBy('label')->get();
    foreach ($alts as $alt) {
        $clen = strlen($alt->content ?? '');
        echo "[{$alt->label}] len={$clen}\n";
        echo "content:[\n" . ($alt->content ?? '') . "\n]\n";
        echo "img_path:" . ($alt->image_path ?? 'null') . "\n\n";
    }
}

// Also fetch from API for 2010 to see the raw structure
$url = 'https://api.enem.dev/v1/exams/2010/questions?limit=5&offset=0';
$context = stream_context_create(['http' => ['header' => 'User-Agent: AuditScript/1.0', 'timeout' => 30]]);
$raw = file_get_contents($url, false, $context);
if ($raw) {
    $data = json_decode($raw, true);
    $questions = $data['questions'] ?? $data['data'] ?? [];
    foreach ($questions as $q) {
        $hasAltFiles = false;
        foreach ($q['alternatives'] ?? [] as $a) {
            if (!empty($a['file'])) { $hasAltFiles = true; break; }
        }
        if ($hasAltFiles || !empty($q['files'])) {
            echo "=== API 2010 Q index=" . $q['index'] . " ===\n";
            echo "context[:200]:[" . substr($q['context'] ?? '', 0, 200) . "]\n";
            echo "files:" . json_encode($q['files'] ?? []) . "\n";
            echo "alternatives:\n";
            foreach ($q['alternatives'] ?? [] as $a) {
                echo "  [{$a['letter']}] text=" . substr($a['text'] ?? '', 0, 60) . " | file=" . ($a['file'] ?? 'null') . "\n";
            }
            break;
        }
    }
} else {
    echo "Failed to fetch API 2010\n";
}
