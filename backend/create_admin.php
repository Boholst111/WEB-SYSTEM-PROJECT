<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

// Create admin user
$admin = AdminUser::create([
    'username' => 'admin',
    'email' => 'admin@diecastempire.com',
    'password_hash' => Hash::make('admin123'),
    'first_name' => 'Admin',
    'last_name' => 'User',
    'role' => 'super_admin',
    'status' => 'active',
    'permissions' => [],
]);

echo "Admin user created successfully!\n";
echo "Username: admin\n";
echo "Email: admin@diecastempire.com\n";
echo "Password: admin123\n";
echo "Role: super_admin\n";
echo "\nYou can now login at: http://localhost:3000/admin/login\n";
