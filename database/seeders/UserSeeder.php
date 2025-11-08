<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@preview.watch',
            'password' => Hash::make('password'),
            'role' => 'Admin',
            'is_active' => true,
        ]);

        // Create Agency user
        User::create([
            'name' => 'Agency User',
            'username' => 'agency',
            'email' => 'agency@preview.watch',
            'password' => Hash::make('password'),
            'role' => 'Agency',
            'is_active' => true,
        ]);

        // Create Brand user
        User::create([
            'name' => 'Brand User',
            'username' => 'brand',
            'email' => 'brand@preview.watch',
            'password' => Hash::make('password'),
            'role' => 'Brand',
            'is_active' => true,
        ]);
    }
}