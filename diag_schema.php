<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['questions', 'simulation_answers', 'simulations'];
$out = "";

foreach ($tables as $table) {
    $out .= "--- TABLE: $table ---\n";
    $res = DB::select("SHOW CREATE TABLE $table");
    $prop = "Create Table";
    $out .= $res[0]->$prop . "\n\n";
}

file_put_contents('schema_output.txt', $out);
echo "Output written to schema_output.txt\n";
