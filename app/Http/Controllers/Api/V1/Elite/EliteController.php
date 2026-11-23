<?php

namespace App\Http\Controllers\Api\V1\Elite;

use App\Http\Controllers\Controller;
use App\Http\Resources\Elite\EliteResource;
use App\Services\Elite\EliteService;
use Illuminate\Http\JsonResponse;

class EliteController extends Controller
{
    public function __construct(private readonly EliteService $eliteService) {}

    /**
     * Elite tiers; for a signed-in customer each tier also says whether their points reach it.
     */
    public function index(): JsonResponse
    {
        $customerPoints = auth('sanctum')->user()?->total_points;

        $data = $this->eliteService->tiers()->map(fn ($elite) => new EliteResource($elite, $customerPoints));

        return successResponse($data);
    }
}
