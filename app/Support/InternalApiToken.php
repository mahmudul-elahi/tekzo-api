<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Identifies trusted server-to-server calls (Next.js SSR/ISR, admin panel) by the
 * `X-Internal-Token` header. The token must never be exposed to browsers.
 */
class InternalApiToken
{
    public const string HEADER = 'X-Internal-Token';

    public static function matches(Request $request): bool
    {
        $expected = (string) config('services.internal_api.token');
        $given = (string) $request->header(self::HEADER, '');

        return $expected !== '' && $given !== '' && hash_equals($expected, $given);
    }
}
