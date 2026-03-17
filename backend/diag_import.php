<?php

use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- Diagnóstico de Banco de Dados (Importação) ---\n";

$totalQuestions = Question::count();
echo "Total de Questões no Banco: {$totalQuestions}\n";

$linkedQuestions = QuestionImportItem::distinct('question_id')->count();
echo "Questões vinculadas a algum lote: {$linkedQuestions}\n";

$orphans = DB::table('questions')
    ->leftJoin('question_import_items', 'questions.id', '=', 'question_import_items.question_id')
    ->whereNull('question_import_items.id')
    ->count();
echo "Questões ÓRFÃS (sem vínculo com lote): {$orphans}\n";

$recentImports = QuestionImport::latest()->take(5)->get();
echo "\nÚltimos 5 Lotes de Importação:\n";
foreach ($recentImports as $imp) {
    echo "ID: {$imp->id} | Nome: {$imp->batch_name} | Status: {$imp->status} | Processadas: {$imp->processed_questions}/{$imp->total_questions}\n";
}

$institutions = DB::table('questions')->select('institution', DB::raw('count(*) as total'))->groupBy('institution')->get();
echo "\nDistribuição por Instituição (Top 10):\n";
foreach ($institutions->take(10) as $inst) {
    echo "- {$inst->institution}: {$inst->total}\n";
}

$organizations = DB::table('questions')->select('organization', DB::raw('count(*) as total'))->groupBy('organization')->get();
echo "\nDistribuição por Banca (Top 10):\n";
foreach ($organizations->take(10) as $org) {
    echo "- {$org->organization}: {$org->total}\n";
}

echo "\n--- Fim do Diagnóstico ---\n";
