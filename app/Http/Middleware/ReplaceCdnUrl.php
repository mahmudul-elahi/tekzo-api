<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReplaceCdnUrl
{
    private string $s3Base;
    private string $cdnBase;
    private bool $enabled;

    public function __construct()
    {
        $awsUrl = rtrim(config('filesystems.disks.s3.url', ''), '/');
        $bucket = config('filesystems.disks.s3.bucket', '');
        $region = config('filesystems.disks.s3.region', '');

        $this->s3Base  = "https://{$bucket}.s3.{$region}.amazonaws.com";
        $this->cdnBase = $awsUrl;

        $this->enabled = $awsUrl !== '' && $awsUrl !== $this->s3Base;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Feature disabled → zero overhead, pass through
        if (! $this->enabled) {
            return $response;
        }

        // 2. Non-JSON response (HTML, CSS, JS, file downloads) → skip
        if (! str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            return $response;
        }

        $body = $response->getContent();

        // JSON-encodes forward slashes as \/ — match both forms
        $s3Escaped  = str_replace('/', '\/', $this->s3Base);
        $cdnEscaped = str_replace('/', '\/', $this->cdnBase);

        // 3. No S3 URLs in this response → skip (fast short-circuit)
        if (! $body || (! str_contains($body, $this->s3Base) && ! str_contains($body, $s3Escaped))) {
            return $response;
        }

        // 4. Replace S3 domain with CDN domain in the JSON body
        $replaced = str_replace(
            [$this->s3Base, $s3Escaped],
            [$this->cdnBase, $cdnEscaped],
            $body
        );

        if ($response instanceof \Symfony\Component\HttpFoundation\JsonResponse) {
            $response->setJson($replaced);
        } else {
            $response->setContent($replaced);
        }

        return $response;
    }
}
