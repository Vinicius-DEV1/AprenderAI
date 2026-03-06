<?php $e = App\Models\Essay::find(2); if (!$e) { echo "NOT_FOUND\n"; exit; } echo json_encode($e->feedback_json, JSON_PRETTY_PRINT);
