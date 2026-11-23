<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Refurbished;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopProductResourceCollection;
use App\Services\Shop\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefurbishedController extends Controller
{
    public function __construct(private readonly ShopService $shopService) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->shopService->paginateRefurbished(
            [
                'brand' => $request->input('brand'),
                'stock' => $request->input('stock'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort_by' => $request->input('sort_by'),
            ],
            clampPerPage($request->input('per_page'), 12, 48),
        );

        return successResponse(new ShopProductResourceCollection($products));
    }

    public function brand(): JsonResponse
    {
        return successResponse($this->shopService->refurbishedBrands());
    }
}
