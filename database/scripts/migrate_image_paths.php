<?php

use App\Models\Question;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * Script de Manutenção: Migração de Caminhos de Imagem ENEM
 * 
 * Objetivo: Converter caminhos antigos (/storage/enem/...) para os novos (/storage/questions/images/...)
 * utilizando o tamanho do arquivo como digital (fingerprint) para o mapeamento.
 */

$dryRun = in_array('--dry-run', $argv);

echo "--- Iniciando Migração de Caminhos de Imagem ENEM ---\n";
if ($dryRun) echo "[MODO DRY RUN] Nenhuma alteração será gravada.\n";

// 1. Mapear todos os arquivos na estrutura NOVA por tamanho
$newPathsBySize = [];
$years = range(2009, 2023);

foreach ($years as $year) {
    if (Storage::disk('public')->exists("questions/images/{$year}")) {
        $files = Storage::disk('public')->files("questions/images/{$year}");
        foreach ($files as $file) {
            $size = Storage::disk('public')->size($file);
            $newPathsBySize[$year][$size] = $file;
        }
    }
}

echo "Mapeamento da nova estrutura concluído.\n";

// 2. Localizar questões com o padrão antigo no enunciado
$questions = Question::where('statement', 'LIKE', '%/storage/enem/%')->get();

echo "Encontradas " . $questions->count() . " questões com caminhos antigos.\n";

$countMigrated = 0;
$countNotFound = 0;

foreach ($questions as $question) {
    $statement = $question->statement;
    $hasChanges = false;

    // Encontrar todos os matches do padrão antigo
    // ![](/storage/enem/{year}/context/{uuid}.png)
    $pattern = '/!\[(.*?)\]\(\/storage\/enem\/(\d{4})\/context\/(.*?)\)/';
    
    $newStatement = preg_replace_callback($pattern, function ($matches) use (&$hasChanges, $newPathsBySize, &$countMigrated, &$countNotFound, $dryRun, $question) {
        $alt = $matches[1];
        $year = (int)$matches[2];
        $uuidFilename = $matches[3];
        
        $oldPath = "enem/{$year}/context/{$uuidFilename}";
        
        if (!Storage::disk('public')->exists($oldPath)) {
            return $matches[0]; // Arquivo físico não existe mais, manter link (será quebrado de qualquer forma)
        }

        $size = Storage::disk('public')->size($oldPath);

        // Tentar encontrar na nova estrutura pelo mesmo tamanho
        if (isset($newPathsBySize[$year][$size])) {
            $newRelativePath = $newPathsBySize[$year][$size];
            $newUrl = Storage::disk('public')->url($newRelativePath);
            
            $hasChanges = true;
            $countMigrated++;
            
            // Extrair apenas o /storage/... se for URL absoluta e estivermos rodando localmente
            // mas Storage::url() costuma retornar /storage/ se for o disk public
            return "![$alt]({$newUrl})";
        }

        $countNotFound++;
        return $matches[0]; // Mantém o antigo se não encontrou par
    }, $statement);

    if ($hasChanges && !$dryRun) {
        $question->statement = $newStatement;
        $question->save();
        echo "ID {$question->id}: Caminho atualizado.\n";
    } elseif ($hasChanges && $dryRun) {
        echo "ID {$question->id}: Seria atualizado.\n";
    }
}

echo "\n--- Resumo ---\n";
echo "Total Processado: " . $questions->count() . "\n";
echo "Sucesso na Migração: {$countMigrated}\n";
echo "Não Encontrados na Nova Estrutura: {$countNotFound}\n";
echo "----------------------------------------------\n";
