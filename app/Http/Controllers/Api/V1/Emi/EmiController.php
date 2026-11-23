<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Emi;

use App\Http\Controllers\Controller;
use App\Services\Emi\EmiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmiController extends Controller
{
    public function __construct(private readonly EmiService $emiService) {}

    public function index(Request $request): JsonResponse
    {
        $amount = (float) $request->input('amount', 5000);

        return successResponse($this->emiService->plansFor($amount));
    }
}
