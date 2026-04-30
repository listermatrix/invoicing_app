<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user', // default role
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Set specific user data for admin user
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Set specific user data for staff user
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'staff@example.com',
            'role' => 'staff',
        ]);
    }
}
