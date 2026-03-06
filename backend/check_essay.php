<?php
$e = \App\Models\Essay::latest()->first();
if (!$e) {
    echo "No essays found\n";
    exit;
}
echo "ID: " . $e->id . "\n";
echo "STATUS: " . $e->status . "\n";
echo "SCORE: " . $e->score . "\n";
echo "OFF_TOPIC: " . ($e->off_topic ? 'true' : 'false') . "\n\n";

$f = $e->feedback_json ?? [];

// Show top-level keys
echo "=== TOP LEVEL KEYS ===\n";
echo implode(', ', array_keys($f)) . "\n\n";

// Summary
echo "=== SUMMARY ===\n";
echo substr($f['summary'] ?? 'NOT FOUND', 0, 500) . "\n\n";

// Competence feedback
echo "=== COMPETENCE_FEEDBACK (raw) ===\n";
echo json_encode($f['competence_feedback'] ?? 'NOT FOUND', JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Competencies mapped
echo "=== COMPETENCIES (mapped) ===\n";
foreach ($f['competencies'] ?? [] as $k => $v) {
    echo strtoupper($k) . " score:" . ($v['score'] ?? '?') . " just:" . substr($v['justification'] ?? '(empty)', 0, 100) . "\n";
}

echo "\n=== OFF_TOPIC_REASON ===\n";
echo ($f['off_topic_reason'] ?? 'N/A') . "\n";
