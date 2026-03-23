<?php
// DB Audit Script - Read Only
require __DIR__ . '/../backend/vendor/autoload.php';
$app = require_once __DIR__ . '/../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ENEM IMPORT DB AUDIT ===\n\n";

// 1. Count ENEM questions
$total = DB::table('questions')->where('type', 'enem')->count();
$totalAll = DB::table('questions')->count();
echo "Total questions in DB: {$totalAll}\n";
echo "Total ENEM questions: {$total}\n\n";

// 2. Sample 5 ENEM questions - show statement length
$samples = DB::table('questions')
    ->where('type', 'enem')
    ->select('id', 'year', 'statement', 'external_id', 'knowledge_area', 'review_status')
    ->latest()
    ->limit(5)
    ->get();

echo "=== SAMPLE 5 ENEM QUESTIONS (most recent) ===\n";
foreach ($samples as $q) {
    $stmt_len = strlen($q->statement ?? '');
    echo "ID: {$q->id} | Year: {$q->year} | KA: {$q->knowledge_area} | STATUS: {$q->review_status}\n";
    echo "  statement_length: {$stmt_len}\n";
    echo "  statement_preview: " . substr($q->statement ?? '', 0, 200) . "\n";
    echo "  external_id: {$q->external_id}\n";
    
    // Get alternatives
    $alts = DB::table('question_alternatives')->where('question_id', $q->id)->get();
    echo "  alternatives count: " . $alts->count() . "\n";
    foreach ($alts as $alt) {
        $content_len = strlen($alt->content ?? '');
        $has_img = str_contains($alt->content ?? '', '![');
        $img_path = $alt->image_path;
        echo "    {$alt->label}: content_len={$content_len}, has_img_markdown={$has_img}, image_path=" . ($img_path ?? 'null') . "\n";
    }
    echo "\n";
}

// 3. Check for questions where statement is suspiciously short (possible truncation)
$shortStatements = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw('CHAR_LENGTH(statement) < 50')
    ->count();
echo "=== ENEM questions with statement < 50 chars: {$shortStatements} ===\n";

// 4. Check statement column type in DB (MySQL INFORMATION_SCHEMA)
$cols = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME IN ('statement', 'explanation', 'knowledge_area', 'external_id')");
echo "\n=== questions table column types ===\n";
foreach ($cols as $col) {
    echo "  {$col->COLUMN_NAME}: type={$col->DATA_TYPE}, max_len=" . ($col->CHARACTER_MAXIMUM_LENGTH ?? 'N/A (unlimited)') . "\n";
}

// 5. Check question_alternatives column types
$altCols = DB::select("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'question_alternatives' AND COLUMN_NAME IN ('content', 'image_path', 'label')");
echo "\n=== question_alternatives column types ===\n";
foreach ($altCols as $col) {
    echo "  {$col->COLUMN_NAME}: type={$col->DATA_TYPE}, max_len=" . ($col->CHARACTER_MAXIMUM_LENGTH ?? 'N/A (unlimited)') . "\n";
}

// 6. Find questions where image is embedded in alternatives content
$altsWithImg = DB::table('question_alternatives')
    ->whereRaw("content LIKE '%![%'")
    ->count();
echo "\n=== Alternatives with image markdown embedded in content: {$altsWithImg} ===\n";

// 7. Find alternatives with non-null image_path
$altsWithImgPath = DB::table('question_alternatives')
    ->whereNotNull('image_path')
    ->count();
echo "=== Alternatives with image_path column set: {$altsWithImgPath} ===\n";

// 8. Sample of an ENEM question with image in DB to compare
$questionWithImg = DB::table('questions')
    ->join('question_alternatives', 'questions.id', '=', 'question_alternatives.question_id')
    ->where('questions.type', 'enem')
    ->whereRaw("(question_alternatives.content LIKE '%![%' OR question_alternatives.image_path IS NOT NULL)")
    ->select('questions.id', 'questions.year', 'questions.statement', 'questions.external_id')
    ->first();

if ($questionWithImg) {
    echo "\n=== Sample ENEM question with image in alternative ===\n";
    echo "ID: {$questionWithImg->id} | Year: {$questionWithImg->year}\n";
    echo "statement[:300]: " . substr($questionWithImg->statement ?? '', 0, 300) . "\n";
    $alts = DB::table('question_alternatives')->where('question_id', $questionWithImg->id)->get();
    foreach ($alts as $alt) {
        echo "  {$alt->label}: content[:150]=" . substr($alt->content ?? '', 0, 150) . " | img_path=" . ($alt->image_path ?? 'null') . "\n";
    }
}

// 9. Check EnemImportLog
$logs = DB::table('enem_import_logs')->select('id','year','status','inserted_count','ignored_count','error_count')->orderBy('id','desc')->limit(5)->get();
echo "\n=== Latest 5 EnemImportLogs ===\n";
foreach ($logs as $log) {
    echo "  ID:{$log->id} | Year:{$log->year} | Status:{$log->status} | Inserted:{$log->inserted_count} | Ignored:{$log->ignored_count} | Errors:{$log->error_count}\n";
}
