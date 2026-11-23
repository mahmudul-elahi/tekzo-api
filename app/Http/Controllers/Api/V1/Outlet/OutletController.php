<?php

namespace App\Http\Controllers\Api\V1\Outlet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Outlet\OutletResource;
use App\Services\Outlet\OutletService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class OutletController extends Controller
{
    public function __construct(
        private OutletService $outletService,
        private PageSeoService $pageSeo,
    ) {}

    public function index(): JsonResponse
    {
        return successResponse([
            'outlets' => OutletResource::collection($this->outletService->active()),
            'seoData' => $this->pageSeo->forOutletPage('outlets'),
        ]);
    }

    public function outletDetail(string $slug): JsonResponse
    {
        $outlet = $this->outletService->findActive($slug);

        if (! $outlet) {
            return errorResponse('Outlet not found', 404);
        }

        return successResponse($outlet);
    }
}
