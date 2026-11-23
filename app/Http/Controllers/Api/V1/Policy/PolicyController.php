<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Policy;

use App\Http\Controllers\Controller;
use App\Models\Widget\Widget;
use App\Services\Policy\PolicyService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class PolicyController extends Controller
{
    public function __construct(
        private readonly PolicyService $policyService,
        private readonly PageSeoService $pageSeo,
    ) {}

    public function index(): JsonResponse
    {
        return successResponse($this->policyService->listed());
    }

    public function policyType(string $type): JsonResponse
    {
        $widget = $this->policyService->findByType($type);

        if (! $widget instanceof Widget) {
            return errorResponse([], 404);
        }

        return successResponse([
            'name' => $widget->name,
            'value' => $widget->value,
            'seoData' => $this->pageSeo->forPolicyPage($type),
        ]);
    }
}
