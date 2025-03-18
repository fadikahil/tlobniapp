<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create roles if they don't exist
        Role::firstOrCreate(['name' => 'Client']);
        Role::firstOrCreate(['name' => 'Business']);
        Role::firstOrCreate(['name' => 'Expert']);

        // Create a test client
        $client = User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'name' => 'Test Client',
                'password' => Hash::make('password'),
                'mobile' => '1234567890',
                'address' => 'Test Location'
            ]
        );
        $client->assignRole('Client');

        // Create a test business
        $business = User::firstOrCreate(
            ['email' => 'business@test.com'],
            [
                'name' => 'Test Business',
                'password' => Hash::make('password'),
                'mobile' => '2345678901',
                'address' => 'Test Location'
            ]
        );
        $business->assignRole('Business');

        // Create a test expert
        $expert = User::firstOrCreate(
            ['email' => 'expert@test.com'],
            [
                'name' => 'Test Expert',
                'password' => Hash::make('password'),
                'mobile' => '3456789012',
                'address' => 'Test Location'
            ]
        );
        $expert->assignRole('Expert');

        $this->command->info('Test users created successfully!');
    }
} 