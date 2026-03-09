<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Essay;
use App\Jobs\EvaluateEssayJob;

echo "--- VERICACAO DO SISTEMA DE REDACOES ---\n";

// 1. Get the admin user
$user = User::where('email', 'admin@aprenderai.com')->first();
if (!$user) {
    die("User not found!");
}

// 2. Create a test essay payload
$essayContent = "A persistência da violência contra a mulher na sociedade brasileira é um problema grave e complexo de raízes históricas e culturais que exige enfrentamento rigoroso. Em primeiro lugar, deve-se ressaltar que o Brasil ainda apresenta resquícios patriarcais. Isso se reflete, por exemplo, nas elevadas taxas de feminicídio apontadas pelo Atlas da Violência, mostrando que as leis, embora existam, não bastam para mudar o comportamento social. Em segundo lugar, o silenciamento das vítimas e o medo das denúncias agravam o caso. Portanto, o Ministério da Educação (MEC) deve atuar desde a base por meio de campanhas em escolas promovendo debates sobre respeito às mulheres, enquanto o Governo Federal precisa ampliar as redes de acolhimento físico para que a impunidade seja mitigada.";

$essay = Essay::create([
    'user_id' => $user->id,
    'type' => 'enem',
    'title' => 'Caminhos para combater a violência contra a mulher no Brasil',
    'time_limit' => 3600,
    'input_type' => 'text',
    'content' => $essayContent,
    'status' => 'pending',
    'started_at' => now(),
]);

echo "Criada redacao #{$essay->id}...\n";

// 3. Submit directly through the job logic to trace everything synchronously
echo "Executando EvaluateEssayJob sincronicamente...\n";
$job = new EvaluateEssayJob($essay);
$job->handle(app(\App\Services\AI\AIService::class));

// 4. Fetch the updated essay and validate the data
$essay->refresh();

echo "\n--- RESULTADO DA AVALIACAO ---\n";
echo "Status: " . $essay->status . "\n";
echo "Score: " . $essay->score . "\n";
echo "Off Topic: " . ($essay->off_topic ? 'SIM (ERRO)' : 'NAO (OK)') . "\n";
echo "AI Suggestions (String): " . (empty($essay->ai_suggestions) ? 'VAZIO (ERRO)' : 'PREENCHIDO (OK)') . "\n";
echo "Improved Version (Database): " . (empty($essay->improved_version) ? 'VAZIO (ERRO)' : 'PREENCHIDO (OK)') . "\n";

$json = is_string($essay->feedback_json) ? json_decode($essay->feedback_json, true) : $essay->feedback_json;

echo "Feedback JSON Summary: " . (empty($json['summary']) ? 'VAZIO (ERRO)' : 'PREENCHIDO (OK)') . "\n";
echo "Feedback JSON Strengths: " . (empty($json['strengths']) ? 'VAZIO (ERRO)' : 'PREENCHIDO (OK)') . "\n";
echo "Feedback JSON Competencies C1/C2/C3/C4/C5: " . (isset($json['competence_scores']['c1']) ? 'PREENCHIDO (OK)' : 'VAZIO (ERRO)') . "\n";

echo "--- FIM DA VERICACAO ---\n";
