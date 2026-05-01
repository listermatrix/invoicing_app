<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Customers', function () {
    it('lists all customers for any authenticated user', function () {
        actingAsUser();
        Customer::factory(5)->create();

        $this->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('creates a customer', function () {
        actingAsUser();

        $this->postJson('/api/v1/customers', [
            'name' => 'Acme Corp',
            'email' => 'billing@acme.com',
            'phone' => '+233201234567',
            'address' => '123 Main St, Accra',
        ])->assertStatus(200)
            ->assertJsonFragment(['name' => 'Acme Corp']);
    });

    it('fails when required fields are missing', function () {
        actingAsUser();

        $this->postJson('/api/v1/customers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    });

    it('updates a customer', function () {
        actingAsUser();
        $customer = Customer::factory()->create();

        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    });

    it('deletes a customer', function () {
        actingAsUser();
        $customer = Customer::factory()->create();

        $this->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();
        $this->assertModelExists($customer);
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    });
});
