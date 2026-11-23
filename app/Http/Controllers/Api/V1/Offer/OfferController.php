<?php

namespace App\Http\Controllers\Api\V1\Offer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Offer\OfferProductResource;
use App\Http\Resources\Offer\OfferResourceCollection;
use App\Services\Offer\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function __construct(private readonly OfferService $offerService) {}

    public function index(Request $request): JsonResponse
    {
        $offers = $this->offerService->paginateActive(clampPerPage($request->input('per_page'), 12, 48));

        return successResponse(new OfferResourceCollection($offers));
    }

    /**
     * Not wrapped in "data", as the frontend expects.
     */
    public function offerProduct(string $slug, Request $request): JsonResponse
    {
        $result = $this->offerService->offerWithProducts($slug, clampPerPage($request->input('per_page'), 12, 48));

        if (! $result) {
            return responseMessage('Offer not found', 404);
        }

        ['offer' => $offer, 'products' => $products] = $result;

        return response()->json([
            'offer' => $offer,
            'products' => $products->getCollection()
                ->map(fn ($product) => (new OfferProductResource($product, $offer))->resolve($request))
                ->toArray(),
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
            ],
        ]);
    }
}
