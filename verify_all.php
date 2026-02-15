<?php

use App\Models\User;
use App\Models\Question;
use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- VERIFICATION START ---\n";

// 1. Check Users (Should be preserved)
$userCount = User::count();
echo "Users: {$userCount}\n";

// 2. Import ENEM (2022 only)
echo "Running Import (2022)...\n";
Artisan::call('enem:import', ['--from' => '2022', '--to' => '2022']);
echo Artisan::output();

// 3. Check ENEM Questions (Strict Source Check)
$enemCount = Question::where('type', 'enem')->where('source', 'enem_real_2009_2023')->count();
echo "Real ENEM Questions (enem_real_2009_2023): {$enemCount}\n";

$portuguese = Question::where('type', 'enem')->where('subject', 'português')->where('source', 'enem_real_2009_2023')->count();
$math = Question::where('type', 'enem')->where('subject', 'matemática')->where('source', 'enem_real_2009_2023')->count();
echo "Português Real: {$portuguese}\n";
echo "Matemática Real: {$math}\n";

// 4. Run Seeder
echo "Running Seeder...\n";
Artisan::call('db:seed', ['--class' => 'QuestionSeeder']);
echo Artisan::output();

// 5. Check Generated Questions (Strict Source Check)
$genCount = Question::where('type', 'enem')->where('source', 'generated_system')->count();
echo "Generated Questions (generated_system): {$genCount}\n";

echo "--- VERIFICATION END ---\n";
