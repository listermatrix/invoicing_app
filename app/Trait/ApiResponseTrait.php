<?php

namespace App\Trait;


use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{

    protected function respondWithResource($resource, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(array_merge(
            ['message' => $message],
            $resource->toArray(request())
        ), $code);
    }

    protected function respondWithData($data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
