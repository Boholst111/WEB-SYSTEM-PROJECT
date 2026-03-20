<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// Get the first user (your account)
$user = User::first();

if ($user) {
    $user->role = 'admin';
    $user->save();
    
    echo "User updated to admin!\n";
    echo "Email: {$user->email}\n";
    echo "Name: {$user->first_name} {$user->last_name}\n";
    echo "Role: {$user->role}\n";
} else {
    echo "No users found in database.\n";
}
