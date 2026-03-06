<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\DB::transaction(function () {
        $idBase = 1; // LINGUA PORTUGUESA
        $idDuplicado = 11; // PORTUGUÊS

        echo "Merging ID {$idDuplicado} into ID {$idBase}...\n";

        // Atualiza os relacionamentos nas questões
        $affected = \Illuminate\Support\Facades\DB::table('question_subject')
            ->where('subject_id', $idDuplicado)
            ->update(['subject_id' => $idBase]);

        echo "Updated {$affected} question relationships.\n";

        // Remove duplicatas que possam ter surgido na tabela pivot (questões que tinham ambos IDs)
        // (Isso é um SQL bruto pq o Eloquent não lida bem com isso em pivot direto)
        $duplicates = \Illuminate\Support\Facades\DB::table('question_subject')
            ->select('question_id', 'subject_id', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('question_id', 'subject_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            \Illuminate\Support\Facades\DB::table('question_subject')
                ->where('question_id', $dup->question_id)
                ->where('subject_id', $dup->subject_id)
                ->limit($dup->count - 1)
                ->delete();
        }
        echo "Cleaned up extra pivot duplicates.\n";

        // Deleta a matéria duplicada
        \App\Models\Subject::where('id', $idDuplicado)->delete();
        echo "Deleted duplicated subject ID 11.\n";
    });
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
