<?php
$email = 'admin@aprenderai.com';
$user = \App\Models\User::where('email', $email)->first();

if (!$user) {
    echo "SITUATION: admin@aprenderai.com NOT FOUND\n";
    exit;
}

echo "SITUATION: admin@aprenderai.com FOUND\n";
echo "HASH: " . $user->password . "\n";

$isPassword = \Illuminate\Support\Facades\Hash::check('password', $user->password);
$isAprova = \Illuminate\Support\Facades\Hash::check('Aprova@123', $user->password);

echo "MATCHES 'password': " . ($isPassword ? 'yes' : 'no') . "\n";
echo "MATCHES 'Aprova@123': " . ($isAprova ? 'yes' : 'no') . "\n";
