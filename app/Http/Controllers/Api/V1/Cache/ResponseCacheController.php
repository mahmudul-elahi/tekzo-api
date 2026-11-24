<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Cache;

use App\Http\Controllers\Controller;
use App\Services\Cache\ResponseCache;
use App\Support\InternalApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseCacheController extends Controller
{
    public function __construct(private readonly ResponseCache $responseCache) {}

    /**
     * Flush cached homepage responses. Called by the admin panel after content changes.
     *
     * Requires the `X-Internal-Token` header to match `INTERNAL_API_TOKEN`.
     */
    public function destroy(Request $request): JsonResponse
    {
        if (! InternalApiToken::matches($request)) {
            return responseMessage('Forbidden', 403);
        }

        $this->responseCache->flush();

        return responseMessage('Response cache flushed');
    }
}
