<?php
$logPath = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    preg_match_all('/\[2026-03-06 23:(39|40|41|42|43|44).*GLOBAL EXCEPTION CAUGHT:.*?^(?=\[2026-03-06|$)/ms', $content, $matches);
    if (!empty($matches[0])) {
        // Obter os últimos 2 matches completos
        $latest = array_slice($matches[0], -2);
        foreach ($latest as $match) {
            echo "=====================================\n";
            echo substr($match, 0, 1500) . "...\n";
        }
    } else {
        echo "Exceção não encontrada no log nas últimas dezenas de minutos.\n";
        // fallback
        echo substr($content, -1500);
    }
} else {
    echo "Log file not found.\n";
}
