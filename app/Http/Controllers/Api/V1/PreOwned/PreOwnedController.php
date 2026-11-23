<?php

namespace App\Http\Controllers\Api\V1\PreOwned;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreOwned\PreOwnedCardResource;
use App\Http\Resources\PreOwned\PreOwnedResourceCollection;
use App\Services\PreOwned\PreOwnedService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreOwnedController extends Controller
{
    public function __construct(
        private readonly PreOwnedService $preOwnedService,
        private readonly PageSeoService $pageSeo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $preOwneds = $this->preOwnedService->paginateModels(
            [
                'brand' => $request->filled('brand') ? $request->input('brand') : null,
                'category' => $request->filled('category') ? $request->input('category') : null,
                'search' => $request->filled('search') ? $request->input('search') : null,
                'stock' => $request->get('stock'),
                'min_price' => $request->get('min_price'),
                'max_price' => $request->get('max_price'),
                'sort_by' => $request->get('sort_by'),
            ],
            clampPerPage($request->input('per_page'), 12, 48),
        );

        return successResponse(PreOwnedResourceCollection::make($preOwneds));
    }

    public function preOwnedProduct(string $slug, Request $request): JsonResponse
    {
        $result = $this->preOwnedService->unitsOfProduct(
            $slug,
            [
                'condition' => $request->input('condition'),
                'storage' => $request->input('storage'),
                'stock' => $request->input('stock'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort_by' => $request->input('sort_by'),
            ],
            clampPerPage($request->input('per_page'), 12, 48),
        );

        if (! $result) {
            return responseMessage(message: 'Product not found', code: 404);
        }

        ['first' => $first, 'units' => $units] = $result;

        return successResponse([
            'preOwneds' => $units->map(fn ($preOwned) => (new PreOwnedCardResource($preOwned))->resolve($request)),
            'pagination' => [
                'current_page' => $units->currentPage(),
                'last_page' => $units->lastPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
            ],
            'brand' => $first->product?->brand,
            'seoData' => $this->preOwnedService->productListingSeo($first),
        ]);
    }

    public function brands(): JsonResponse
    {
        return successResponse($this->preOwnedService->brandsWithAvailableUnits());
    }

    /**
     * Home/landing style listing: one "shelf" per product model (e.g. iPhone 13 Pro Max,
     * iPhone 14), each shelf showing a handful of its individual pre-owned units (each
     * with its own condition/battery health/price), plus a total count so the frontend
     * can show a "See All" link to `pre-owneds/{slug}/products` for that model.
     */
    public function grouped(Request $request): JsonResponse
    {
        ['products' => $products, 'unitsByProduct' => $unitsByProduct] = $this->preOwnedService->shelves(
            array_filter(explode(',', (string) $request->input('brand', ''))),
            max(1, (int) $request->input('limit', 4)),
            clampPerPage($request->input('per_page'), 6, 48),
        );

        $groups = $products->map(fn ($product) => [
            'product' => [
                'id' => $product->id,
                'name' => $product->product_name,
                'slug' => $product->slug,
            ],
            'total_available' => $product->available_pre_owned_count,
            'preOwneds' => $unitsByProduct->get($product->id, collect())
                ->map(fn ($preOwned) => (new PreOwnedCardResource($preOwned))->resolve($request))
                ->values(),
        ])->values();

        return successResponse([
            'groups' => $groups,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'seoData' => $this->pageSeo->forMarketplacePage('marketplace'),
        ]);
    }
}
