<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Services\EnemImportService;
use Illuminate\Support\Facades\DB;

echo "=== TESTE DE UPSERT DA VPS ===\n\n";

DB::beginTransaction();
try {
    // 1. Criar Payload Fake da API (Ano 2023, Index 999, Contexto Vazio)
    $apiQuestion = [
        'year' => 2023,
        'index' => 999,
        'context' => '',
        'alternativesIntroduction' => 'Enunciado real que a API manda.',
        'files' => [],
        'discipline' => 'matematica',
        'language' => null,
        'alternatives' => [
            ['letter' => 'A', 'text' => 'Nova Alt A', 'file' => null, 'isCorrect' => true],
            ['letter' => 'B', 'text' => 'Nova Alt B', 'file' => null, 'isCorrect' => false]
        ]
    ];

    // 2. Simular o Estado da VPS (Bug antigo)
    $oldHash = md5("ENEM|2023|INEP|Estudante|"); // context era vazio então ficava vazio no final

    $legacyQuestion = Question::create([
        'external_id' => $oldHash,
        'type' => 'enem',
        'format' => 'multiple_choice',
        'difficulty' => 'medium',
        'year' => 2023,
        'statement' => '**Enunciado em negrito errado que o bug gerava**', // Texto bugado
        'source' => 'api',
        'theme' => null,
        'knowledge_area' => 'matematica',
        'organization' => 'ENEM',
        'institution' => 'INEP',
        'role' => 'Estudante',
        'review_status' => 'approved',
    ]);

    QuestionAlternative::create(['question_id' => $legacyQuestion->id, 'label' => 'A', 'content' => 'Velha Alt A', 'is_correct' => true]);
    QuestionAlternative::create(['question_id' => $legacyQuestion->id, 'label' => 'B', 'content' => 'Velha Alt B', 'is_correct' => false]);

    echo "[VPS SIMULADO CRIADO] QID: {$legacyQuestion->id} | Hash: {$oldHash} | Statement: {$legacyQuestion->statement}\n\n";

    // 3. Rodar o Novo Importador (O Teste Real)
    $service = new EnemImportService();
    $result = $service->processQuestion($apiQuestion);

    echo "RESULTADO DO IMPORTADOR: " . $result['status'] . "\n\n";

    if ($result['status'] === 'updated') {
        $legacyQuestion->refresh();
        echo "[SUCESSO] Questão foi atualizada (mesmo ID: {$legacyQuestion->id})!\n";
        echo "NOVO STATEMENT: {$legacyQuestion->statement}\n";
        echo "NOVO HASH (EXTERNAL_ID): {$legacyQuestion->external_id}\n";
        
        $alts = $legacyQuestion->alternatives()->get();
        echo "ALTERNATIVAS (Devem ser as Novas):\n";
        foreach ($alts as $alt) {
            echo " - {$alt->label}: {$alt->content}\n";
        }

        // Verifica o Hash Forte Correto esperado: "ENEM|2023|INEP|Estudante|Enunciado real que a API manda.|999"
        $expectedNewHash = md5("ENEM|2023|INEP|Estudante|Enunciado real que a API manda.|999");
        if ($legacyQuestion->external_id === $expectedNewHash) {
            echo "\n🎉 TESTE PASSOU! O HASH DA VPS FOI CURADO PARA O HASH FORTE CORRETO!\n";
        } else {
            echo "\n❌ TESTE FALHOU! O novo hash não é o esperado. Recebido: {$legacyQuestion->external_id} | Esperado: {$expectedNewHash}\n";
        }

    } else {
        echo "❌ TESTE FALHOU! Esperava 'updated', recebeu '{$result['status']}'.\n\n";
        print_r($result);
    }

} catch (\Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "\nRollback executado para limpar o teste.\n";
}
