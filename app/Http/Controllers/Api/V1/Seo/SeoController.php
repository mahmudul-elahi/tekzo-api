<?php

namespace App\Http\Controllers\Api\V1\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class SeoController extends Controller
{
    public function __construct(private readonly PageSeoService $pageSeo) {}

    public function seo(string $pageSlug): JsonResponse
    {
        ['page' => $page, 'seo' => $seo] = $this->pageSeo->findPageAndSeo($pageSlug);

        if (! $page) {
            return responseMessage('Page not found', 404);
        }

        if (! $seo) {
            return responseMessage('Seo not found', 404);
        }

        return successResponse($this->pageSeo->formatInfoPage($page, $seo));
    }
}
