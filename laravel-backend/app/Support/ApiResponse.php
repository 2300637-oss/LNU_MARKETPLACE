<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiResponse
{
    public static function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'trace_id' => self::traceId(),
        ], $status);
    }

    public static function error(string $message, mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'trace_id' => self::traceId(),
        ], $status);
    }

    private static function traceId(): string
    {
        $request = request();
        $traceId = $request->attributes->get('trace_id');

        if (! is_string($traceId) || $traceId === '') {
            $traceId = (string) Str::uuid();
            $request->attributes->set('trace_id', $traceId);
        }

        return $traceId;
    }
}
