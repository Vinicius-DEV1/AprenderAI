<?php

$key = "AIzaSyAWhZ2pGpiwB_Ytm_Ai24nVcVw0Q9Ke-tI";

// Models to try in order
$models = ["gemini-2.0-flash", "gemini-1.5-flash", "gemini-2.0-flash-exp"];

function testModel($key, $modelName)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=$key";

    echo "Testing Model: $modelName\n";
    echo "URL: $url\n";

    $payload = [
        "contents" => [[
                "parts" => [["text" => "Diga 'Olá Mundo' de forma bem humorada."]]
            ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Status Code: $httpCode\n";
    if ($error) {
        echo "CURL Error: $error\n";
    }

    echo "Response Body:\n";
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    else {
        echo $response . "\n";
    }
    echo "--------------------------------------------------\n";
    return $httpCode === 200;
}

foreach ($models as $model) {
    if (testModel($key, $model)) {
        echo "SUCCESS: Key works for generation with model $model.\n";
        break;
    }
}
