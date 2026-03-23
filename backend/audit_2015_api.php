<?php
$json = file_get_contents('/var/www/full_2015.json');
$allQuestions = json_decode($json, true);

$total = count($allQuestions);
$hasContext = 0;
$hasIntro = 0;
$hasFiles = 0;

foreach ($allQuestions as $q) {
    if (!empty($q['context'])) $hasContext++;
    if (!empty($q['alternativesIntroduction'])) $hasIntro++;
    if (!empty($q['files'])) $hasFiles++;
}

echo "RESULTADO AUDITORIA API 2015:\n";
echo "Total Questões: {$total}\n";
echo "Com Contexto: {$hasContext} (" . round(($hasContext/$total)*100, 1) . "%)\n";
echo "Com Introdução: {$hasIntro} (" . round(($hasIntro/$total)*100, 1) . "%)\n";
echo "Com Arquivos: {$hasFiles} (" . round(($hasFiles/$total)*100, 1) . "%)\n";

$target = "cisterna";
foreach ($allQuestions as $q) {
    if (stripos(json_encode($q), $target) !== false) {
        echo "\n=== DADOS RAW DA QUESTÃO CISTERNA (Index {$q['index']}) ===\n";
        print_r($q);
    }
}
