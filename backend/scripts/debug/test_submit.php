<?php
$user = \App\Models\User::find(1);
$essay = \App\Models\Essay::where('user_id', 1)->latest()->first();

$request = \Illuminate\Http\Request::create("/api/v1/essays/{$essay->id}/submit", 'POST', ['content' => 'Teste']);
$request->setUserResolver(function () use ($user) {
    return $user; });
$request->headers->set('Accept', 'application/json');

$kernel = app(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
if ($response->exception) {
    echo "Exception Trace:\n" . $response->exception->getTraceAsString() . "\n";
}
