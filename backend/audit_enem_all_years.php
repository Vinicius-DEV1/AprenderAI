<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

ob_start();

echo "=== ENEM AUDIT — TODOS OS ANOS ===\n\n";

// 1. Distribuição por ano
$byYear = DB::table('questions')
    ->where('type', 'enem')
    ->select('year', DB::raw('count(*) as cnt'))
    ->groupBy('year')
    ->orderBy('year')
    ->get();

echo "--- Questões ENEM por ano (DB) ---\n";
$totalEnem = 0;
foreach ($byYear as $row) {
    echo "  Year {$row->year}: {$row->cnt} questões\n";
    $totalEnem += $row->cnt;
}
echo "  TOTAL: {$totalEnem}\n\n";

// 2. Distribuição por knowledge_area
$byKA = DB::table('questions')
    ->where('type', 'enem')
    ->select('knowledge_area', DB::raw('count(*) as cnt'))
    ->groupBy('knowledge_area')
    ->orderByDesc('cnt')
    ->get();
echo "--- Por knowledge_area ---\n";
foreach ($byKA as $row) {
    echo "  [{$row->knowledge_area}]: {$row->cnt}\n";
}
echo "\n";

// 3. Tamanho dos enunciados por ano
echo "--- Statement length stats por ano ---\n";
$years = $byYear->pluck('year')->toArray();
foreach ($years as $yr) {
    $stats = DB::table('questions')
        ->where('type', 'enem')
        ->where('year', $yr)
        ->selectRaw('MIN(CHAR_LENGTH(statement)) as min_len, MAX(CHAR_LENGTH(statement)) as max_len, AVG(CHAR_LENGTH(statement)) as avg_len, COUNT(*) as cnt')
        ->first();
    $shortCount = DB::table('questions')
        ->where('type', 'enem')
        ->where('year', $yr)
        ->whereRaw('CHAR_LENGTH(statement) < 20')
        ->count();
    echo "  Year {$yr}: cnt={$stats->cnt}, min={$stats->min_len}, max={$stats->max_len}, avg=" . round($stats->avg_len) . ", stmt<20chars={$shortCount}\n";
}
echo "\n";

// 4. Alternativas por ano — imagens
echo "--- Alternativas com imagem — por ano ---\n";
foreach ($years as $yr) {
    $qIds = DB::table('questions')->where('type','enem')->where('year',$yr)->pluck('id');
    $withMd = DB::table('question_alternatives')
        ->whereIn('question_id', $qIds)
        ->whereRaw("content LIKE '%![%'")
        ->count();
    $withPath = DB::table('question_alternatives')
        ->whereIn('question_id', $qIds)
        ->whereNotNull('image_path')
        ->count();
    $doubled = DB::table('question_alternatives')
        ->whereIn('question_id', $qIds)
        ->whereRaw("content LIKE '%![%'")
        ->whereNotNull('image_path')
        ->count();
    $totalAlts = DB::table('question_alternatives')
        ->whereIn('question_id', $qIds)
        ->count();
    echo "  Year {$yr}: total_alts={$totalAlts}, img_md={$withMd}, img_path={$withPath}, double_embed={$doubled}\n";
}
echo "\n";

// 5. Questões cujo statement é vazio ou só whitespace
echo "--- Questões com statement vazio ou curto ---\n";
$empty = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw("TRIM(statement) = '' OR statement IS NULL")
    ->get(['id', 'year', 'knowledge_area', 'external_id']);
echo "  Count statement vazio: " . $empty->count() . "\n";
foreach ($empty as $q) {
    echo "    ID:{$q->id} Year:{$q->year} KA:{$q->knowledge_area}\n";
}

$veryShort = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw("CHAR_LENGTH(TRIM(statement)) BETWEEN 1 AND 50")
    ->get(['id', 'year', 'knowledge_area', 'statement']);
echo "  Count statement 1-50 chars: " . $veryShort->count() . "\n";
foreach ($veryShort->take(10) as $q) {
    echo "    ID:{$q->id} Year:{$q->year} KA:{$q->knowledge_area} | stmt:[{$q->statement}]\n";
}
echo "\n";

// 6. Alternativas onde content é SOMENTE markdown (sem texto antes da imagem)
echo "--- Alternativas onde content é SOMENTE markdown de imagem ---\n";
$imgOnly = DB::table('question_alternatives')
    ->whereRaw("content REGEXP '^[[:space:]]*!\\[.*\\]\\(.*\\)[[:space:]]*$'")
    ->count();
echo "  Count alt image-only (sem texto puro): {$imgOnly}\n";

// Também verificar por ano
foreach ($years as $yr) {
    $qIds = DB::table('questions')->where('type','enem')->where('year',$yr)->pluck('id');
    $imgOnlyYr = DB::table('question_alternatives')
        ->whereIn('question_id', $qIds)
        ->whereRaw("content REGEXP '^[[:space:]]*!\\[.*\\]\\(.*\\)[[:space:]]*$'")
        ->count();
    if ($imgOnlyYr > 0) {
        echo "  Year {$yr}: {$imgOnlyYr} alternativas image-only\n";
    }
}
echo "\n";

// 7. Questões onde statement contém imagem duplicada (aparece 2x o mesmo src)
echo "--- Questões com imagem duplicada no statement ---\n";
$dupImg = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw("statement LIKE '%Imagem de Apoio%' AND statement LIKE '%Imagem do enunciado%'")
    ->count();
$dupImg2 = DB::table('questions')
    ->where('type', 'enem')
    ->whereRaw("(LENGTH(statement) - LENGTH(REPLACE(statement, '![', ''))) / LENGTH('![') >= 2")
    ->count();
echo "  Com 'Imagem de Apoio' + 'Imagem do enunciado': {$dupImg}\n";
echo "  Com 2+ ocorrências de '![' no statement: {$dupImg2}\n\n";

// 8. Alternativas com texto text NÃO vazio mas que tem imagem no file (verifique se o texto foi preservado)
// Aqui comparamos via API — vamos buscar 1 questão com alt.file e ver o conteúdo salvo
$sampleWithImg = DB::table('questions')
    ->join('question_alternatives', 'questions.id', '=', 'question_alternatives.question_id')
    ->where('questions.type', 'enem')
    ->whereNotNull('question_alternatives.image_path')
    ->select('questions.id', 'questions.year', 'questions.knowledge_area', 'questions.statement')
    ->groupBy('questions.id', 'questions.year', 'questions.knowledge_area', 'questions.statement')
    ->limit(5)
    ->get();

echo "--- Amostras de questões com imagem em alternativas (DB) ---\n";
foreach ($sampleWithImg as $q) {
    $alts = DB::table('question_alternatives')->where('question_id', $q->id)->orderBy('label')->get();
    echo "  ID:{$q->id} Year:{$q->year} KA:{$q->knowledge_area}\n";
    echo "  stmt[:200]:[" . substr($q->statement ?? '', 0, 200) . "]\n";
    foreach ($alts as $alt) {
        $clen = strlen($alt->content ?? '');
        $has_md = str_contains($alt->content ?? '', '![');
        $has_path = !empty($alt->image_path);
        $has_text_only = $clen > 0 && !$has_md;
        echo "    [{$alt->label}] len={$clen}, img_md=" . ($has_md ? 'Y' : 'n') . ", img_path=" . ($has_path ? 'Y' : 'n') . ", text_only=" . ($has_text_only ? 'Y' : 'n') . "\n";
        if ($has_md) {
            echo "         content:[" . substr($alt->content ?? '', 0, 100) . "]\n";
        }
    }
    echo "\n";
}

// 9. EnemImportLogs
$logs = DB::table('enem_import_logs')
    ->select('id','year','status','inserted_count','ignored_count','error_count','created_at')
    ->orderBy('id','desc')
    ->limit(10)
    ->get();
echo "--- EnemImportLogs (todos) ---\n";
foreach ($logs as $log) {
    echo "  ID:{$log->id} Year:{$log->year} Status:{$log->status} Ins:{$log->inserted_count} Ign:{$log->ignored_count} Err:{$log->error_count} At:{$log->created_at}\n";
}
echo "\n";

// 10. review_status distribution
$byStatus = DB::table('questions')
    ->where('type', 'enem')
    ->select('review_status', DB::raw('count(*) as cnt'))
    ->groupBy('review_status')
    ->get();
echo "--- review_status distribution (ENEM) ---\n";
foreach ($byStatus as $row) {
    echo "  " . ($row->review_status ?? 'null') . ": {$row->cnt}\n";
}
echo "\n";

$output = ob_get_clean();
echo $output;
file_put_contents('/var/www/audit_enem_all_years.txt', $output);
echo "[Saved to /var/www/audit_enem_all_years.txt]\n";
