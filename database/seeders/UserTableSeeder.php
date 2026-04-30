<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );


        User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'first_name' => 'Staff',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );

    }
}
