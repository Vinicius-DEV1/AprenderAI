<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

ob_start();

echo "=== ENEM IMPORT DB AUDIT ===\n\n";
$total = DB::table('questions')->where('type', 'enem')->count();
$totalAll = DB::table('questions')->count();
echo "Total questions (all): {$totalAll}\n";
echo "Total ENEM questions: {$total}\n\n";

$cols = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME IN ('statement', 'explanation', 'knowledge_area', 'external_id', 'image_path')");
echo "--- questions column types ---\n";
foreach ($cols as $col) {
    $maxLen = $col->CHARACTER_MAXIMUM_LENGTH ?? 'NULL(text-unlimited)';
    echo "  {$col->COLUMN_NAME}: type={$col->DATA_TYPE}, max_len={$maxLen}\n";
}
echo "\n";

$altCols = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'question_alternatives' AND COLUMN_NAME IN ('content', 'image_path', 'label')");
echo "--- question_alternatives column types ---\n";
foreach ($altCols as $col) {
    $maxLen = $col->CHARACTER_MAXIMUM_LENGTH ?? 'NULL(text-unlimited)';
    echo "  {$col->COLUMN_NAME}: type={$col->DATA_TYPE}, max_len={$maxLen}\n";
}
echo "\n";

$samples = DB::table('questions')
    ->where('type', 'enem')
    ->select('id', 'year', 'statement', 'external_id', 'knowledge_area', 'review_status', 'image_path')
    ->latest()
    ->limit(5)
    ->get();

echo "--- SAMPLE 5 ENEM QUESTIONS ---\n";
foreach ($samples as $q) {
    $stmt_len = strlen($q->statement ?? '');
    echo "ID:{$q->id} Year:{$q->year} KA:{$q->knowledge_area} STATUS:{$q->review_status}\n";
    echo "  stmt_len:{$stmt_len}\n";
    echo "  stmt_preview:[" . substr($q->statement ?? '', 0, 200) . "]\n";
    echo "  q.image_path:" . ($q->image_path ?? 'null') . "\n";
    $alts = DB::table('question_alternatives')->where('question_id', $q->id)->orderBy('label')->get();
    echo "  alts:\n";
    foreach ($alts as $alt) {
        $clen = strlen($alt->content ?? '');
        $has_img_md = str_contains($alt->content ?? '', '![');
        echo "    [{$alt->label}] len={$clen}, img_md=" . ($has_img_md ? 'YES' : 'no') . ", img_path=" . ($alt->image_path ?? 'null') . "\n";
    }
    echo "\n";
}

$shortStatements = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw('CHAR_LENGTH(statement) < 10')
    ->count();
$bw50 = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw('CHAR_LENGTH(statement) BETWEEN 10 AND 100')
    ->count();
echo "--- Statement length distribution ---\n";
echo "ENEM questions with statement < 10 chars: {$shortStatements}\n";
echo "ENEM questions with statement 10-100 chars: {$bw50}\n\n";

$altImgMd = DB::table('question_alternatives')
    ->whereRaw("content LIKE '%![%'")
    ->count();
$altImgPath = DB::table('question_alternatives')
    ->whereNotNull('image_path')
    ->count();
echo "--- Image audit ---\n";
echo "Alts with embedded image markdown: {$altImgMd}\n";
echo "Alts with image_path set: {$altImgPath}\n\n";

// Find alt where content has image markdown AND image_path is set (double-embedding)
$doubled = DB::table('question_alternatives')
    ->whereRaw("content LIKE '%![%'")
    ->whereNotNull('image_path')
    ->count();
echo "Alts with BOTH markdown AND image_path (double-embed): {$doubled}\n\n";

// Sample question with image alt
$sampleImg = DB::table('questions')
    ->join('question_alternatives', 'questions.id', '=', 'question_alternatives.question_id')
    ->where('questions.type', 'enem')
    ->whereNotNull('question_alternatives.image_path')
    ->select('questions.id', 'questions.year', 'questions.statement', 'questions.external_id', 'questions.knowledge_area')
    ->first();

if ($sampleImg) {
    echo "--- Sample ENEM Q with image_path in alt ---\n";
    echo "ID:{$sampleImg->id} Year:{$sampleImg->year} KA:{$sampleImg->knowledge_area}\n";
    echo "stmt[:300]:[" . substr($sampleImg->statement ?? '', 0, 300) . "]\n";
    $alts = DB::table('question_alternatives')->where('question_id', $sampleImg->id)->orderBy('label')->get();
    foreach ($alts as $alt) {
        $clen = strlen($alt->content ?? '');
        echo "  [{$alt->label}] len={$clen}\n";
        echo "    content:[" . substr($alt->content ?? '', 0, 200) . "]\n";
        echo "    img_path:" . ($alt->image_path ?? 'null') . "\n";
    }
    echo "\n";
}

$logs = DB::table('enem_import_logs')->select('id','year','status','inserted_count','ignored_count','error_count')->orderBy('id','desc')->limit(5)->get();
echo "--- EnemImportLogs ---\n";
if ($logs->isEmpty()) {
    echo "  none\n";
} else {
    foreach ($logs as $log) {
        echo "  ID:{$log->id} Year:{$log->year} Status:{$log->status} Inserted:{$log->inserted_count} Ignored:{$log->ignored_count} Errors:{$log->error_count}\n";
    }
}

$output = ob_get_clean();
echo $output;
file_put_contents('/var/www/audit_enem_result.txt', $output);
echo "\n[Saved to /var/www/audit_enem_result.txt]\n";
