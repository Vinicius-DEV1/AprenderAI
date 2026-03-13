<?php

use App\Models\Question;
use Illuminate\Support\Facades\DB;

$start = microtime(true);
$query = Question::select(
    'arquivo_origem',
    'year',
    'organization',
    'institution',
    'role',
    DB::raw('COUNT(*) as total_questions'),
    DB::raw('MAX(updated_at) as last_update'),
    DB::raw('MAX(created_at) as max_created')
)
    ->groupBy('arquivo_origem', 'year', 'organization', 'institution', 'role')
    ->limit(5);

$results = $query->get()->toArray();
$time = microtime(true) - $start;

echo json_encode(['time' => $time, 'results' => $results], JSON_PRETTY_PRINT);
