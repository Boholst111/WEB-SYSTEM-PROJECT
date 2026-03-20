<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== CREATING TEST REGULAR USER ===\n\n";

// Check if test user already exists
$existingUser = User::where('email', 'user@diecastempire.com')->first();

if ($existingUser) {
    echo "Test user already exists!\n";
    echo "Email: {$existingUser->email}\n";
    echo "Name: {$existingUser->first_name} {$existingUser->last_name}\n";
    echo "Role: " . ($existingUser->role ?? 'user') . "\n";
    exit(0);
}

// Create test regular user
$user = User::create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'email' => 'user@diecastempire.com',
    'password_hash' => Hash::make('password123'),
    'phone' => '+639171234567',
    'date_of_birth' => '1995-05-15',
    'loyalty_tier' => 'bronze',
    'loyalty_credits' => 0.00,
    'total_spent' => 0.00,
    'status' => 'active',
    'role' => 'user',
    'preferences' => [],
]);

echo "✅ Test regular user created successfully!\n\n";
echo "Email: user@diecastempire.com\n";
echo "Password: password123\n";
echo "Name: Test Customer\n";
echo "Role: user\n";
echo "Loyalty Tier: bronze\n";
echo "\nYou can now login with this account to test regular user features!\n";
