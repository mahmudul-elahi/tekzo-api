<?php

namespace App\Http\Controllers\Api\V1\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\Category\CategoryResource;
use App\Http\Resources\Category\CategoryTreeResource;
use App\Http\Resources\News\NewsResource;
use App\Http\Resources\PreOwned\PreOwnedResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\Reels\ReelsVideoResource;
use App\Http\Resources\Search\SearchProductResource;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Services\Category\CategoryService;
use App\Services\Home\HomeService;
use App\Services\Search\ProductSearchService;
use App\Services\Seo\PageSeoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomePageController extends Controller
{
    public function __construct(
        private readonly HomeService $homeService,
        private readonly CategoryService $categoryService,
        private readonly ProductSearchService $searchService,
        private readonly PageSeoService $pageSeo,
    ) {}

    /**
     * Returned without the usual "data" wrapper, as the frontend expects.
     */
    public function homeCategory(): Collection
    {
        return $this->categoryService->homeCategories();
    }

    public function categories(): JsonResponse
    {
        return successResponse($this->categoryService->leafCategoriesInTreeOrder());
    }

    public function categoryTree(): JsonResponse
    {
        return successResponse(CategoryTreeResource::collection($this->categoryService->tree()));
    }

    public function brand(): JsonResponse
    {
        return successResponse(
            $this->homeService->activeBrands(['id', 'name', 'slug', 'tagline', 'shop_banner', 'primary_logo', 'secondary_logo', 'updated_at'])
        );
    }

    public function allBrand(): JsonResponse
    {
        return successResponse([
            'brands' => $this->homeService->activeBrands(['id', 'name', 'slug', 'tagline', 'shop_banner', 'primary_logo', 'secondary_logo']),
            'seoData' => $this->pageSeo->forPage('brands'),
        ]);
    }

    public function categoryProducts(): JsonResponse
    {
        return successResponse([
            'categories' => CategoryResource::collection($this->categoryService->categoriesWithTopProductsInTreeOrder()),
            'seoData' => $this->pageSeo->forPage('categories'),
        ]);
    }

    public function slider(): JsonResponse
    {
        return successResponse($this->homeService->slidersAndBanners());
    }

    public function flashSaleProduct(): JsonResponse
    {
        return successResponse(ProductResource::collection($this->homeService->flashSaleBlockProducts()));
    }

    public function flashSale(): JsonResponse
    {
        $flashSale = $this->homeService->currentFlashSale();

        if (! $flashSale) {
            return successResponse(['products' => [], 'offer' => null]);
        }

        return successResponse([
            'products' => ProductResource::collection($flashSale['products']),
            'offer' => $this->homeService->flashSaleSummary($flashSale['offer']),
            'seoData' => $this->pageSeo->forPage('flash-sale'),
        ]);
    }

    public function featuredProduct(): JsonResponse
    {
        return successResponse(ProductResource::collection($this->homeService->featuredProducts()));
    }

    public function newArrivalProduct(): JsonResponse
    {
        return successResponse(ProductResource::collection($this->homeService->latestArrivals()));
    }

    public function newArrival(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 12), 48);

        return successResponse(new ShopProductResourceCollection($this->homeService->paginateNewArrivals($perPage)));
    }

    public function news(): JsonResponse
    {
        return successResponse([
            'news' => NewsResource::collection($this->homeService->news()),
            'seoData' => $this->pageSeo->forPage('news'),
        ]);
    }

    public function blog(Request $request): JsonResponse
    {
        $blogs = $this->homeService->paginateNews(clampPerPage($request->input('per_page'), 12, 48));

        return successResponse([
            'news' => NewsResource::collection($blogs),
            'pagination' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ],
            'seoData' => $this->pageSeo->forPage('blog'),
        ]);
    }

    public function searchProduct(Request $request): JsonResponse
    {
        $products = $this->searchService->suggestions($request->input('query', ''));

        if (! $products instanceof \Illuminate\Support\Collection) {
            return successResponse(['products' => []]);
        }

        return successResponse(SearchProductResource::collection($products));
    }

    public function searchProductResult(Request $request): JsonResponse
    {
        $products = $this->searchService->results($request->input('query', ''), clampPerPage($request->input('per_page'), 12, 48));

        if (! $products instanceof LengthAwarePaginator) {
            return successResponse([
                'products' => [],
                'seoData' => null,
            ]);
        }

        return successResponse(new ShopProductResourceCollection($products));
    }

    public function reelsVideo(): JsonResponse
    {
        return successResponse(ReelsVideoResource::collection($this->homeService->reels()));
    }

    public function homePreOwnedProduct(): JsonResponse
    {
        return successResponse(PreOwnedResource::collection($this->homeService->latestPreOwneds()));
    }
}
