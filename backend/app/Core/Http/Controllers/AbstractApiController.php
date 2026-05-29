<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class AbstractApiController extends Controller
{
    /**
     * @param  array<string, mixed>|object|null  $data
     */
    protected function ok(array|object|null $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function okWithMeta(array|object|null $data, array $meta, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    protected function accepted(?string $message = null): JsonResponse
    {
        return response()->json([
            'message' => $message ?? __('Accepted'),
        ], 202);
    }

    /**
     * @param  array<string, mixed>|object|null  $data
     */
    protected function created(array|object|null $data): JsonResponse
    {
        return $this->ok($data, 201);
    }

    protected function error(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
