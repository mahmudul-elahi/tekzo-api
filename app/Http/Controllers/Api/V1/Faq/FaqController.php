<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Faq;

use App\Http\Controllers\Controller;
use App\Services\Faq\FaqService;
use App\Services\Seo\PageSeoService;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function __construct(
        private readonly FaqService $faqService,
        private readonly PageSeoService $pageSeo,
    ) {}

    public function index(): JsonResponse
    {
        return successResponse([
            'faqs' => $this->faqService->grouped(),
            'seoData' => $this->pageSeo->forInfoPage('faq') ?? [],
        ]);
    }
}
