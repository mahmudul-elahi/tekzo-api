<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Career;

use App\Http\Controllers\Controller;
use App\Http\Resources\Career\CareerDetailResource;
use App\Http\Resources\Career\CareerResource;
use App\Models\Career\Career;
use App\Services\Career\CareerService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class CareerController extends Controller
{
    public function __construct(
        private readonly CareerService $careerService,
        private readonly PageSeoService $pageSeo,
    ) {}

    public function index(): JsonResponse
    {
        return successResponse([
            'jobs' => CareerResource::collection($this->careerService->openPositions()),
            'seoData' => $this->pageSeo->forInfoPage('career') ?? [],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $career = $this->careerService->findOpenPosition($slug);

        if (! $career instanceof Career) {
            return errorResponse('Career not found', 404);
        }

        return successResponse(new CareerDetailResource($career));
    }
}
