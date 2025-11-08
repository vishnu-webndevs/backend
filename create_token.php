<?php
require_once 'vendor/autoload.php';

// Bootstrap Laravel app
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get or create a user
$user = \App\Models\User::first();
if (!$user) {
    $user = \App\Models\User::create([
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now()
    ]);
}

// Create token
$token = $user->createToken('test-token')->plainTextToken;

echo "User: " . $user->email . "\n";
echo "Token: " . $token . "\n";