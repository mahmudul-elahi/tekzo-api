<?php

use Illuminate\Http\JsonResponse;

if (!function_exists('responseMessage')) {
    /**
     * Return a JSON response for successful operations.
     *
     * @param mixed|null $message
     * @param int $code HTTP status code (default: 200).
     * @return JsonResponse
     */
    function responseMessage(mixed $message = null, int $code = 200, $data = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
        ], $code);
    }
}
if (!function_exists('successResponse')) {
    /**
     * Return a JSON response for successful operations.
     *
     * @param mixed|null $data Additional data to include in the response.
     * @param int $code HTTP status code (default: 200).
     * @return JsonResponse
     */
    function successResponse(mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], $code);
    }
}

if (!function_exists('errorResponse')) {
    /**
     * Return a JSON response for error scenarios.
     *
     * @param mixed|null $data Additional data to include in the response.
     * @param int $code HTTP status code (default: 400).
     * @return JsonResponse
     */
    function errorResponse(mixed $data = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], $code);
    }
}

if (!function_exists('to_cdn_url')) {
    function to_cdn_url(?string $url): ?string
    {
        if (!$url) return null;

        $s3Base  = 'https://' . config('filesystems.disks.s3.bucket') . '.s3.' . config('filesystems.disks.s3.region') . '.amazonaws.com';
        $cdnBase = rtrim(config('filesystems.disks.s3.url', ''), '/');

        if (!$cdnBase || $cdnBase === $s3Base) return $url;

        return str_replace($s3Base, $cdnBase, $url);
    }
}
