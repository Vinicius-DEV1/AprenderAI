<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- SESSION & AUTH AUDIT ---\n";

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

echo "--- SESSION & AUTH AUDIT (RAW GUZZLE) ---\n";

$baseUrl = 'http://webserver';
$jar = new CookieJar();
$client = new Client([
    'base_uri' => $baseUrl,
    'cookies' => $jar,
    'headers' => [
        'Host' => 'localhost',
        'Accept' => 'application/json',
    ]
]);

// 1. Get CSRF Cookie
echo "1. GET /sanctum/csrf-cookie ... ";
$response = $client->get('/sanctum/csrf-cookie');
echo "STATUS: " . $response->getStatusCode() . "\n";

// DEBUG: Guzzle may not associate 'webserver' cookies with 'localhost' Host header automatically for the Jar
echo "SET-COOKIES: " . implode(', ', $response->getHeader('Set-Cookie')) . "\n";

// Manually sync cookies to localhost domain if Guzzle is being strict
foreach ($response->getHeader('Set-Cookie') as $cookieStr) {
    if (str_contains($cookieStr, 'XSRF-TOKEN')) {
        $c = SetCookie::fromString($cookieStr);
        $c->setDomain('localhost');
        $jar->setCookie($c);
    }
}

echo "COOKIES AFTER CSRF: " . json_encode($jar->toArray(), JSON_PRETTY_PRINT) . "\n";

// 2. Login
echo "\n2. POST /api/v1/login ... ";
$response = $client->post('/api/v1/login', [
    'headers' => [
        'Referer' => 'http://localhost:5174',
        'X-XSRF-TOKEN' => $jar->getCookieByName('XSRF-TOKEN')?->getValue()
    ],
    'json' => [
        'email' => 'admin@aprenderai.com',
        'password' => 'password'
    ]
]);

echo "STATUS: " . $response->getStatusCode() . "\n";

// Manually sync session cookie
foreach ($response->getHeader('Set-Cookie') as $cookieStr) {
    if (str_contains($cookieStr, 'laravel-session')) {
        $c = SetCookie::fromString($cookieStr);
        $c->setDomain('localhost');
        $jar->setCookie($c);
    }
}
echo "COOKIES AFTER LOGIN: " . json_encode($jar->toArray(), JSON_PRETTY_PRINT) . "\n";


// 3. Access Protected Route
echo "\n3. GET /api/v1/user ... ";
try {
    $response = $client->get('/api/v1/user', [
        'headers' => [
            'Referer' => 'http://localhost:5174',
        ],
    ]);
    echo "STATUS: " . $response->getStatusCode() . "\n";
    echo "BODY: " . $response->getBody() . "\n";
} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo "STATUS: " . $e->getResponse()->getStatusCode() . "\n";
    echo "BODY: " . $e->getResponse()->getBody() . "\n";
}



