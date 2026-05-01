<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\CustomerCollection;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Trait\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponseTrait;
    public function index(Request $request): JsonResponse
    {
        $products = Product::latest()->paginate(15);

        return $this->respondWithResource(
            new ProductCollection($products),
            'Products retrieved successfully'
        );

    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->respondWithData(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    public function show(Request $request, Product $product): JsonResponse
    {

        return $this->respondWithData(
           new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return $this->respondWithData(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }
}
