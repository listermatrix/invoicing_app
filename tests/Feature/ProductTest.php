<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Products', function () {
    it('lists all products for any authenticated user', function () {
        actingAsUser();
        Product::factory(5)->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('creates a product', function () {
        actingAsUser();

        $this->postJson('/api/v1/products', [
            'name' => 'Widget Pro',
            'description' => 'A great widget',
            'unit_price' => 49.99,
            'stock_quantity' => 100,
        ])->assertStatus(200)
            ->assertJsonFragment(['name' => 'Widget Pro', 'stock_quantity' => 100]);
    });

    it('fails when required fields are missing', function () {
        actingAsUser();

        $this->postJson('/api/v1/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'unit_price', 'stock_quantity']);
    });

    it('rejects negative stock', function () {
        actingAsUser();

        $this->postJson('/api/v1/products', [
            'name' => 'Widget',
            'unit_price' => 10,
            'stock_quantity' => -5,
        ])->assertUnprocessable();
    });

    it('updates a product', function () {
        actingAsUser();
        $product = Product::factory()->create(['stock_quantity' => 50]);

        $this->putJson("/api/v1/products/{$product->id}", ['stock_quantity' => 75])
            ->assertOk()
            ->assertJsonFragment(['stock_quantity' => 75]);
    });

    it('deletes a product', function () {
        actingAsUser();
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}")->assertForbidden();
        $this->assertModelExists($product);
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    });
});
