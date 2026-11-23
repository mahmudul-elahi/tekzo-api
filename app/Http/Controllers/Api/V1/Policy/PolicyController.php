<?php

namespace App\Http\Controllers\Api\V1\Policy;

use App\Http\Controllers\Controller;
use App\Services\Policy\PolicyService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class PolicyController extends Controller
{
    public function __construct(
        private PolicyService $policyService,
        private PageSeoService $pageSeo,
    ) {}

    public function index(): JsonResponse
    {
        return successResponse($this->policyService->listed());
    }

    public function policyType(string $type): JsonResponse
    {
        $widget = $this->policyService->findByType($type);

        if (! $widget) {
            return errorResponse([], 404);
        }

        return successResponse([
            'name' => $widget->name,
            'value' => $widget->value,
            'seoData' => $this->pageSeo->forPolicyPage($type),
        ]);
    }
}
