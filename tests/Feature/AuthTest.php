<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Login', function () {
    it('logs in with valid credentials and returns token', function () {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'first_name', 'last_name', 'email', 'role', 'token'],
            ]);
    });

    it('rejects invalid credentials', function () {
        User::factory()->create(['email' => 'john@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ])->assertUnprocessable();
    });

    it('rejects non-existent email', function () {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();
    });
});

describe('Logout', function () {
    it('logs out authenticated user', function () {
        actingAsUser();
        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);
    });

    it('rejects unauthenticated logout', function () {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    });
});

describe('Me', function () {
    it('returns authenticated user without token', function () {
        actingAsUser();

        $response = $this->getJson('/api/v1/auth/me')->assertOk();

        expect($response->json('data'))->not->toHaveKey('token');
    });

    it('rejects unauthenticated request', function () {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});
