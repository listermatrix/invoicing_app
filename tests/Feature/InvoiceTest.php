<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invoicePayload(Customer $customer, array $items = [], string $status = 'draft'): array
{
    return [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => $status,
        'items' => $items ?: [
            [
                'description' => 'Consulting service',
                'unit_price' => 100.00,
                'quantity' => 2,
            ],
        ],
    ];
}

describe('Invoice Creation', function () {
    it('creates a draft invoice with items and does not deduct stock', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10, 'unit_price' => 50]);

        $this->postJson('/api/v1/invoices', invoicePayload($customer, [
            ['product_id' => $product->id, 'description' => $product->name, 'unit_price' => 50, 'quantity' => 4],
        ]))->assertStatus(200);

        expect($product->fresh()->stock_quantity)->toBe(10);
    });

    it('creates a sent invoice and deducts stock immediately', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10, 'unit_price' => 50]);

        $this->postJson('/api/v1/invoices', invoicePayload($customer, [
            ['product_id' => $product->id, 'description' => $product->name, 'unit_price' => 50, 'quantity' => 4],
        ], 'sent'))->assertStatus(200);
        expect($product->fresh()->stock_quantity)->toBe(6);
    });

    it('calculates total amount correctly', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();

        // Create products (optional - if you still want to verify product exists)
        $productA = Product::factory()->create([
            'unit_price' => 100.00,
            'stock_quantity' => 10
        ]);

        $productB = Product::factory()->create([
            'unit_price' => 50.00,
            'stock_quantity' => 10
        ]);

        $this->postJson('/api/v1/invoices', invoicePayload($customer, [
            [
                'product_id' => $productA->id,
                'description' => 'Service A',
                'unit_price' => 100.00,
                'quantity' => 2
            ],
            [
                'product_id' => $productB->id,
                'description' => 'Service B',
                'unit_price' => 50.00,
                'quantity' => 3
            ],
        ]))->assertStatus(200)
            ->assertJsonFragment(['total' => "350.00"]);
    });

    it('requires at least one item', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'items' => [],
        ])->assertUnprocessable();
    });

    it('rejects due date before issue date', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();

        $this->postJson('/api/v1/invoices', [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'items' => [['description' => 'x', 'unit_price' => 10, 'quantity' => 1]],
        ])->assertUnprocessable();
    });

    it('rejects invoice when product stock is insufficient', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 2]);

        $this->postJson('/api/v1/invoices', invoicePayload($customer, [
            ['product_id' => $product->id, 'description' => $product->name, 'unit_price' => 10, 'quantity' => 5],
        ], 'sent'))->assertUnprocessable();

        expect($product->fresh()->stock_quantity)->toBe(2);
    });

    it('requires authentication', function () {
        $this->postJson('/api/v1/invoices', [])->assertUnauthorized();
    });
});

describe('Invoice Listing', function () {
    it('staff user sees only their own invoices', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        Invoice::factory(3)->create(['user_id' => $user->id, 'customer_id' => $customer->id]);
        Invoice::factory(2)->create(['customer_id' => $customer->id]);

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('admin sees all invoices', function () {
        $customer = Customer::factory()->create();
        Invoice::factory(3)->create(['customer_id' => $customer->id]);

        actingAsAdmin();

        $this->getJson('/api/v1/invoices')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('Invoice Authorization', function () {
    it('creator can update their own invoice', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'customer_id' => $customer->id]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Updated note'])
            ->assertOk();
    });

    it('staff cannot update another user invoice', function () {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        actingAsUser();

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    });

    it('admin can update any invoice', function () {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        actingAsAdmin();

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['notes' => 'Admin update'])
            ->assertOk();
    });

    it('staff cannot delete an invoice', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'customer_id' => $customer->id]);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")
            ->assertForbidden();
    });

    it('admin can delete any invoice', function () {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        actingAsAdmin();

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertOk();
        $this->assertModelMissing($invoice);
    });
});

describe('Invoice Stock Management', function () {
    it('deducts stock when draft transitions to sent', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'unit_price' => $product->unit_price,
            'quantity' => 3,
            'amount' => $product->unit_price * 3,
        ]);

        $this->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'sent'])
            ->assertOk();

        expect($product->fresh()->stock_quantity)->toBe(7);
    });

    it('restores stock when sent invoice is cancelled', function () {
        $user = actingAsUser();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $invoice = $this->postJson('/api/v1/invoices', invoicePayload($customer, [
            ['product_id' => $product->id, 'description' => $product->name, 'unit_price' => 10, 'quantity' => 3],
        ], 'sent'))->json('data');

        expect($product->fresh()->stock_quantity)->toBe(7);

        $this->putJson("/api/v1/invoices/{$invoice['id']}", ['status' => 'cancelled'])
            ->assertOk();

        expect($product->fresh()->stock_quantity)->toBe(10);
    });

    it('restores stock when a sent invoice is deleted by admin', function () {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $staffUser = User::factory()->create(['role' => 'staff']);
        $invoice = Invoice::factory()->create([
            'user_id' => $staffUser->id,
            'customer_id' => $customer->id,
            'status' => 'sent',
        ]);
        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'unit_price' => $product->unit_price,
            'quantity' => 4,
            'amount' => $product->unit_price * 4,
        ]);
        $product->decrementStock(4);

        actingAsAdmin();

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertOk();

        expect($product->fresh()->stock_quantity)->toBe(10);
    });

    it('does not restore stock when a draft invoice is deleted', function () {
        $user = actingAsAdmin();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);
        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'unit_price' => $product->unit_price,
            'quantity' => 3,
            'amount' => $product->unit_price * 3,
        ]);

        $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertOk();

        expect($product->fresh()->stock_quantity)->toBe(10);
    });
});
