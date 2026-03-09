<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Essay;
use App\Jobs\EvaluateEssayJob;

echo "--- STARTING ESSAY EVALUATION TEST ---\n";

$user = User::first() ?? User::factory()->create();

$essay = Essay::create([
    'user_id' => $user->id,
    'title' => 'O impacto do uso de inteligência artificial na educação brasileira',
    'content' => 'A inteligência artificial tem trazido muitos benefícios para a educação, mas também desafios. Por um lado, ajuda os alunos a aprenderem mais rápido. Por outro, pode gerar dependência e falta de pensamento crítico. É preciso que as escolas adotem tecnologias de forma consciente, com professores orientando o uso. Dessa forma, podemos garantir um futuro onde a IA seja uma ferramenta, não um substituto para a educação humana. Para isso, o MEC deve criar diretrizes claras.',
    'type' => 'enem',
    'status' => 'evaluating', // Trigger job logic
]);

echo "Created Essay ID: {$essay->id}\n";
echo "Dispatching EvaluateEssayJob synchronously...\n";

try {
    $job = new EvaluateEssayJob($essay);
    $job->handle(app(\App\Services\AI\AIService::class));

    $essay->refresh();

    echo "--- FINAL RESULTS ---\n";
    echo "Score: {$essay->score}\n";
    echo "Off Topic: " . ($essay->off_topic ? 'TRUE' : 'FALSE') . "\n";
    echo "Status: {$essay->status}\n";
    echo "Improved Version Length: " . strlen((string) $essay->improved_version) . "\n";

    $feedback = is_string($essay->feedback_json) ? json_decode($essay->feedback_json, true) : $essay->feedback_json;
    echo "Has C1-C5? " . (isset($feedback['competencies']) || isset($feedback['competence_scores']) ? 'YES' : 'NO') . "\n";
    echo "Summary: " . substr((string) $feedback['summary'], 0, 100) . "...\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "--- END TEST ---\n";
