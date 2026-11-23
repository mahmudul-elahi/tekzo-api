<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Services\Seo\PageSeoService;
use App\Services\Shop\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService,
        private readonly PageSeoService $pageSeo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $shops = $this->shopService->paginateShop(
            [
                'brand' => $request->input('brand'),
                'category' => $request->input('category'),
                'stock' => $request->input('stock'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort_by' => $request->input('sort_by'),
            ],
            min((int) $request->input('per_page', 12), 48),
            $request->input('page', 1),
        );

        return successResponse([
            'products' => new ShopProductResourceCollection($shops),
            'seoData' => $this->pageSeo->forShopPage('shop', '/shop'),
        ]);
    }

    /**
     * Slugs for the sitemap.
     */
    public function product(): JsonResponse
    {
        return successResponse($this->shopService->sitemapEntries());
    }
}
