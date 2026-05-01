<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerCollection;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Trait\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::withCount('invoices')
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return $this->respondWithResource(
            new CustomerCollection($customers),
            'Customers retrieved successfully'
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());
        return $this->respondWithResource(new CustomerResource($customer),
            'Customers retrieved successfully');

    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        return $this->respondWithData(new CustomerResource($customer),
            'Customer retrieved successfully');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());
        return $this->respondWithData(new CustomerResource($customer),
            'Customers updated successfully');
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }
}
