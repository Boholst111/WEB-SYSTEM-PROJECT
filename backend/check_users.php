<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== CHECKING USERS IN DATABASE ===\n\n";

$users = User::all(['id', 'email', 'first_name', 'last_name', 'role']);

echo "Total users: " . $users->count() . "\n\n";

foreach ($users as $user) {
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Name: {$user->first_name} {$user->last_name}\n";
    echo "Role: " . ($user->role ?? 'user') . "\n";
    echo "---\n";
}

// Check if we have both admin and regular user
$adminCount = User::where('role', 'admin')->count();
$regularCount = User::where('role', 'user')->orWhereNull('role')->count();

echo "\nSummary:\n";
echo "Admin users: {$adminCount}\n";
echo "Regular users: {$regularCount}\n";

if ($adminCount === 0) {
    echo "\n⚠️  No admin users found!\n";
} else {
    echo "\n✅ Admin user(s) exist\n";
}

if ($regularCount === 0) {
    echo "⚠️  No regular users found!\n";
} else {
    echo "✅ Regular user(s) exist\n";
}
