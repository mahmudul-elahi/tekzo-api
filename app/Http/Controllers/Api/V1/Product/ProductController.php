<?php

namespace App\Http\Controllers\Api\V1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreOwned\PreOwnedResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Product\ProductReviewCollection;
use App\Http\Resources\Product\SingleBrandProductResource;
use App\Http\Resources\Product\SingleCategoryProductResource;
use App\Http\Resources\Product\SinglePreOwnedProductResource;
use App\Http\Resources\Product\SingleProductResource;
use App\Services\Product\ProductListingService;
use App\Services\Product\ProductReviewService;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private ProductReviewService $reviewService,
        private ProductListingService $listingService,
    ) {}

    /**
     * Product detail, or a pre-owned item's detail when `preOwnedId` is given.
     */
    public function detail(string $slug, Request $request): JsonResponse
    {
        $preOwnedId = $request->input('preOwnedId');

        if ($preOwnedId) {
            $preOwned = $this->productService->findPreOwnedDetail($preOwnedId, $request);

            if (! $preOwned) {
                return errorResponse([], 404);
            }

            return successResponse(SinglePreOwnedProductResource::make($preOwned));
        }

        $product = $this->productService->findProductDetail($slug, $request);

        if (! $product) {
            return errorResponse([], 404);
        }

        return successResponse(SingleProductResource::make($product));
    }

    public function productReview(string $slug, Request $request): JsonResponse
    {
        $result = $this->reviewService->forProduct($slug, clampPerPage($request->input('per_page'), 5, 48));

        if (! $result) {
            return errorResponse([], 404);
        }

        $summary = $result['summary'];

        return successResponse([
            'average' => (float) ($summary->avg_rating ?? 0),

            'total' => (int) ($summary->total_reviews ?? 0),

            'stars' => [
                5 => (int) ($summary->five_star ?? 0),
                4 => (int) ($summary->four_star ?? 0),
                3 => (int) ($summary->three_star ?? 0),
                2 => (int) ($summary->two_star ?? 0),
                1 => (int) ($summary->one_star ?? 0),
            ],
            'reviews' => new ProductReviewCollection($result['reviews']),
        ]);
    }

    public function relatedProduct(string $slug): JsonResponse
    {
        $relatedProducts = $this->productService->relatedProducts($slug);

        if ($relatedProducts === null) {
            return errorResponse([], 404);
        }

        return successResponse(ProductResource::collection($relatedProducts));
    }

    /**
     * Lightweight category lookup with just what the hero banner needs (name, banner),
     * so it can render without waiting on the paginated product list from categoryProduct().
     */
    public function categoryInfo(string $slug): JsonResponse
    {
        $category = $this->listingService->findCategoryBanner($slug);

        if (! $category) {
            return errorResponse([], 404);
        }

        return successResponse([
            'name' => $category->name,
            'banner' => $category->banner,
        ]);
    }

    public function categoryProduct(string $slug, Request $request): JsonResponse
    {
        $category = $this->listingService->categoryWithProducts($slug, [
            'brand' => $request->filled('brand') ? $request->input('brand') : null,
            'stock' => $request->input('stock'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'sort_by' => $request->input('sort_by'),
            'per_page' => clampPerPage($request->input('per_page'), 12, 48),
        ]);

        if (! $category) {
            return errorResponse([], 404);
        }

        return successResponse(SingleCategoryProductResource::make($category));
    }

    public function brandProduct(string $slug, Request $request): JsonResponse
    {
        $brand = $this->listingService->brandWithProducts($slug, [
            'category' => $request->filled('category') ? $request->input('category') : null,
            'stock' => $request->input('stock'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'sort_by' => $request->input('sort_by'),
            'per_page' => clampPerPage($request->input('per_page'), 12, 48),
        ]);

        if (! $brand) {
            return errorResponse([], 404);
        }

        return successResponse(SingleBrandProductResource::make($brand));
    }

    public function preOwnedRelatedProduct(string $slug, Request $request): JsonResponse
    {
        $related = $this->productService->relatedPreOwneds($slug, $request->input('preOwnedId'));

        if ($related === null) {
            return errorResponse([], 404);
        }

        return successResponse(PreOwnedResource::collection($related));
    }
}
