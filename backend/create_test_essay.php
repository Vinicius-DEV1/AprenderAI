<?php
// Create a fresh essay for admin user and dispatch evaluation
$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$user) {
    echo "Admin user not found\n";
    exit;
}

$topic = 'Desafios da preservacao do patrimonio cultural imaterial no Brasil';

$essay = \App\Models\Essay::create([
    'user_id' => $user->id,
    'input_type' => 'text',
    'type' => 'enem',
    'title' => $topic,
    'topic' => $topic,
    'content' => 'A valorizacao do patrimonio cultural imaterial brasileiro representa um desafio contemporaneo que exige atencao urgente do poder publico. As festas tradicionais, os saberes artesanais e as praticas religiosas de matriz africana e indigena constituem a identidade nacional e correm risco de desaparecimento diante da homogeneizacao cultural promovida pela globalizacao. Portanto, e imperativo que o Estado assuma compromisso firme com a preservacao dessas manifestacoes. Em primeiro lugar, a ausencia de politicas publicas estruturadas compromete a transmissao desses saberes. Sem financiamento adequado, mestres artesaos e guardioes das tradicoes nao conseguem formar novos aprendizes, interrompendo cadeias de transmissao oral que perdurariam por seculos. Em segundo lugar, o desinteresse crescente das novas geracoes, influenciadas por plataformas digitais globais, agrava o problema. Sem estrategias de valorizacao que dialoguem com o universo jovem, a tendencia e o abandono progressivo dessas praticas culturais. Em conclusao, o governo federal deve, em parceria com estados e municipios, criar um programa nacional de preservacao do patrimonio imaterial, com bolsas para mestres de cultura, acervo digital oficial e insercao curricular no ensino fundamental, garantindo assim que as geracoes futuras herdem uma identidade cultural viva e diversa.',
    'status' => 'pending',
]);

echo "Created essay ID: " . $essay->id . "\n";
echo "Dispatching evaluation job...\n";

\App\Jobs\EvaluateEssayJob::dispatch($essay);
echo "Job dispatched. Monitor with check_essay.php\n";
