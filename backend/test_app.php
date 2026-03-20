<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\UserNotification;

$user = User::first();
UserNotification::where('user_id', $user->id)
    ->where('type', 'essay_pending')
    ->where('related_essay_id', 99999)
    ->delete();

$data = [
    'title'      => 'Redação pendente',
    'body'       => 'Teste',
    'action_url' => null,
    'meta'       => ['essay_id' => 99999]
];

try {
    $exists = UserNotification::where('user_id', $user->id)
        ->where('type', 'essay_pending')
        ->where('related_essay_id', 99999)
        ->whereNull('read_at')
        ->exists();

    if ($exists) {
        echo "ALREADY EXISTS\n";
    } else {
        $n = UserNotification::create(array_merge($data, [
            'user_id'          => $user->id,
            'type'             => 'essay_pending',
            'related_essay_id' => 99999,
        ]));
        echo "CREATED: {$n->id}\n";
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
