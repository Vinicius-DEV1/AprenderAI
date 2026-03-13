<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;
use Illuminate\Support\Facades\DB;

$query = Question::select(
    'arquivo_origem',
    'year',
    'organization',
    'institution',
    'role',
    DB::raw('COUNT(*) as total_questions'),
    DB::raw('CAST(COALESCE(MAX(updated_at), MAX(created_at), MAX(extracted_at)) AS CHAR) as last_update')
)
    ->groupBy('arquivo_origem', 'year', 'organization', 'institution', 'role')
    ->orderBy('last_update', 'desc')
    ->limit(5);

$results = $query->get()->toArray();

echo json_encode($results, JSON_PRETTY_PRINT);
