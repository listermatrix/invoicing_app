<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvoiceService
{
    /**
     * @throws Throwable
     */
    public function create(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $status = $data['status'] ?? 'draft';

            $invoice = Invoice::create([
                'user_id' => $userId,
                'customer_id' => $data['customer_id'],
                'invoice_number' => Invoice::generateNumber(),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items'], deductStock: $status === 'sent');

            $invoice->recalculateTotal();

            return $invoice->load('customer', 'items.product');
        });
    }

    /**
     * @throws Throwable
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $previousStatus = $invoice->status;
            $newStatus = $data['status'] ?? $previousStatus;

            $invoice->update([
                'customer_id' => $data['customer_id'] ?? $invoice->customer_id,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'status' => $newStatus,
                'notes' => $data['notes'] ?? $invoice->notes,
            ]);

            if (isset($data['items'])) {
                if ($previousStatus === 'sent') {
                    $this->restoreStock($invoice);
                }

                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items'], deductStock: $newStatus === 'sent');
                $invoice->recalculateTotal();
            } else {
                $this->handleStatusTransition($invoice, $previousStatus, $newStatus);
            }

            return $invoice->fresh('customer', 'items.product');
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice->status === 'sent') {
                $this->restoreStock($invoice);
            }

            $invoice->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    private function handleStatusTransition(Invoice $invoice, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if ($from === 'draft' && $to === 'sent') {
            $this->deductStockForExistingItems($invoice);
            return;
        }

        if ($from === 'sent' && $to === 'cancelled') {
            $this->restoreStock($invoice);
            return;
        }

        if ($from === 'cancelled' && $to === 'sent') {
            $this->deductStockForExistingItems($invoice);
        }
    }

    private function deductStockForExistingItems(Invoice $invoice): void
    {
        foreach ($invoice->items()->with('product')->get() as $item) {
            if (! $item->product) {
                continue;
            }

            $product = Product::lockForUpdate()->find($item->product_id);

            if (! $product->isInStock($item->quantity)) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for product \"{$product->name}\". Available: {$product->stock_quantity}, Requested: {$item->quantity}.",
                ]);
            }

            $product->decrementStock($item->quantity);
        }
    }

    private function syncItems(Invoice $invoice, array $items, bool $deductStock = false): void
    {
        foreach ($items as $itemData) {
            $productId = $itemData['product_id'] ?? null;
            $unitPrice = $itemData['unit_price'];
            $quantity = $itemData['quantity'];

            if ($productId && $deductStock) {
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
