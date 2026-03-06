<?php
use Illuminate\Support\Facades\Hash;
use App\Models\User;

$email = 'admin@aprenderai.com';
$password = 'Aprova@123';

$user = User::where('email', $email)->first();

if (!$user) {
    $user = User::create([
        'name' => 'Admin AprenderAI',
        'email' => $email,
        'password' => Hash::make($password),
        'email_verified_at' => now(),
        'role' => 'admin', // standard role if necessary
    ]);
    echo "CREATED_USER id={$user->id}\n";
} else {
    echo "USER_ALREADY_EXISTS\n";
}
