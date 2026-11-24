<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Stores full public API responses so repeated homepage reads skip the database.
 *
 * Entries are namespaced by a version number, so flushing only bumps the version
 * and works on every cache store, including those without tag support.
 */
class ResponseCache
{
    private const string VERSION_KEY = 'response-cache:version';

    /**
     * @return array{content: string, status: int, content_type: string}|null
     */
    public function get(Request $request): ?array
    {
        return Cache::get($this->key($request));
    }

    public function put(Request $request, string $content, int $status, string $contentType, int $ttlSeconds): void
    {
        Cache::put($this->key($request), [
            'content' => $content,
            'status' => $status,
            'content_type' => $contentType,
        ], $ttlSeconds);
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    private function key(Request $request): string
    {
        $query = $request->query();
        ksort($query);

        return sprintf('response-cache:%d:%s', $this->version(), sha1($request->path().'?'.http_build_query($query)));
    }
}
