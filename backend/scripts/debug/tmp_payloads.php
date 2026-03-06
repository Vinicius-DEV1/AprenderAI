<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('email', 'admin@aprenderai.com')->first();
auth()->login($user);

$req1 = Illuminate\Http\Request::create('http://localhost/api/v1/dashboard', 'GET');
$req1->setUserResolver(function () use ($user) {
    return $user; });
$res1 = $kernel->handle($req1);
file_put_contents('tmp_dash.json', json_encode(json_decode($res1->getContent()), JSON_PRETTY_PRINT));

$req2 = Illuminate\Http\Request::create('http://localhost/api/v1/study-plan', 'GET');
$req2->setUserResolver(function () use ($user) {
    return $user; });
$res2 = $kernel->handle($req2);
file_put_contents('tmp_plan.json', json_encode(['status' => $res2->getStatusCode(), 'body' => json_decode($res2->getContent())], JSON_PRETTY_PRINT));
