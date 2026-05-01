<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Trait\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InvoiceController extends Controller
{
    use ApiResponseTrait;
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $invoices = $request->user()
            ->invoices()
            ->with('customer')
            ->latest()
            ->paginate(15);

        return  $this->respondWithResource(new InvoiceCollection($invoices),
            'Invoices retrieved successfully');
    }

    /**
     * @throws Throwable
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request->validated(), $request->user()->id);

        return  $this->respondWithData(new InvoiceResource($invoice),
            'Invoices created successfully');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return  $this->respondWithData(new InvoiceResource($invoice),
            'Invoices created successfully');
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $updatedInvoice = $this->invoiceService->update($invoice, $request->validated());
        return  $this->respondWithData(new InvoiceResource($updatedInvoice),
            'Invoice updated successfully');
    }

    /**
     * @throws Throwable
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete($invoice);
        return response()->json(['message' => 'Invoice deleted successfully.']);
    }
}
