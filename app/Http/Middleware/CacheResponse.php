<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Cache\ResponseCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caches successful GET responses of public, non-personalised endpoints.
 *
 * Usage: ->middleware('cache.response') or ->middleware('cache.response:60') for a custom TTL in seconds.
 */
class CacheResponse
{
    public function __construct(private readonly ResponseCache $responseCache) {}

    public function handle(Request $request, Closure $next, ?string $ttlSeconds = null): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $cached = $this->responseCache->get($request);

        if ($cached !== null) {
            return response($cached['content'], $cached['status'])
                ->header('Content-Type', $cached['content_type'])
                ->header('X-Response-Cache', 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->responseCache->put(
                $request,
                (string) $response->getContent(),
                $response->getStatusCode(),
                (string) $response->headers->get('Content-Type', 'application/json'),
                (int) ($ttlSeconds ?? config('services.response_cache.ttl')),
            );
        }

        $response->headers->set('X-Response-Cache', 'MISS');

        return $response;
    }
}
