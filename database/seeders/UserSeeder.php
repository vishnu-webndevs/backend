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
        // Admin user (idempotent)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => 'Admin',
                'is_active' => true,
            ]
        );

        // Agency user (idempotent)
        User::updateOrCreate(
            ['username' => 'agency'],
            [
                'name' => 'Agency User',
                'email' => 'agency@example.com',
                'password' => Hash::make('password'),
                'role' => 'Agency',
                'is_active' => true,
            ]
        );

        // Brand user (idempotent)
        User::updateOrCreate(
            ['username' => 'brand'],
            [
                'name' => 'Brand User',
                'email' => 'brand@example.com',
                'password' => Hash::make('password'),
                'role' => 'Brand',
                'is_active' => true,
            ]
        );
    }
}