<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles table if it doesn't exist
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // Define the roles
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Administrator with full system access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Agency',
                'description' => 'Agency user with ability to manage brand accounts',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Brand',
                'description' => 'Brand user with ability to manage their campaigns',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert the roles into the roles table
        foreach ($roles as $role) {
            // Check if the role already exists to avoid duplicates
            if (!DB::table('roles')->where('name', $role['name'])->exists()) {
                DB::table('roles')->insert($role);
            }
        }
    }
}