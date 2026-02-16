<?php

use App\Models\Question;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$results = [
    'detailed_db_check' => [],
];

$data = DB::table('questions')->select('type', 'subject', 'source', DB::raw('count(*) as total'))->groupBy('type', 'subject', 'source')->get();
foreach ($data as $d) {
    $results['detailed_db_check'][] = [
        'type' => $d->type,
        'subject' => $d->subject,
        'subject_hex' => bin2hex($d->subject),
        'source' => $d->source,
        'count' => $d->total
    ];
}

file_put_contents('diag_results_v3.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Results written to diag_results_v3.json\n";
