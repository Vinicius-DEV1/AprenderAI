<?php
use Illuminate\Support\Facades\Hash;
$u = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$u) {
    echo "NOT_FOUND\n";
} else {
    echo "FOUND id=" . $u->id . " updated_at=" . $u->updated_at . "\n";
    echo Hash::check('Aprova@123', $u->password) ? "PASSWORD_OK\n" : "PASSWORD_MISMATCH\n";
}
