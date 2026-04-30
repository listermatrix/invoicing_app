<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function create(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $invoice = Invoice::create([
                'user_id' => $userId,
                'customer_id' => $data['customer_id'],
                'invoice_number' => Invoice::generateNumber(),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items']);

            $invoice->recalculateTotal();

            return $invoice->load('customer', 'items.product');
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'status' => $data['status'] ?? $invoice->status,
                'notes' => $data['notes'] ?? $invoice->notes,
            ]);

            if (isset($data['items'])) {
                $this->restoreStock($invoice);
                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items']);
                $invoice->recalculateTotal();
            }

            return $invoice->fresh('customer', 'items.product');
        });
    }

    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $this->restoreStock($invoice);
            $invoice->delete();
        });
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            $productId = $itemData['product_id'] ?? null;
            $unitPrice = $itemData['unit_price'];
            $quantity = $itemData['quantity'];

            if ($productId) {
                $product = Product::lockForUpdate()->findOrFail($productId);

                if (! $product->isInStock($quantity)) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for product \"{$product->name}\". Available: {$product->stock_quantity}, Requested: {$quantity}.",
                    ]);
                }

                $product->decrementStock($quantity);
                $unitPrice = $itemData['unit_price'] ?? $product->unit_price;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $productId,
                'description' => $itemData['description'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'amount' => round($unitPrice * $quantity, 2),
            ]);
        }
    }

    private function restoreStock(Invoice $invoice): void
    {
        foreach ($invoice->items()->with('product')->get() as $item) {
            $item->product?->incrementStock($item->quantity);
        }
    }
}
