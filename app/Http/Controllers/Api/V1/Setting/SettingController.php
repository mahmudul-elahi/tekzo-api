<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Setting;

use App\Http\Controllers\Controller;
use App\Services\Seo\PageSeoService;
use App\Services\Setting\SettingService;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly PageSeoService $pageSeo,
    ) {}

    /**
     * Tenant settings plus site SEO. Returns a bare empty array when the tenant is missing.
     */
    public function index(): JsonResponse|array
    {
        $setting = $this->settingService->tenantSettings();

        if ($setting === null) {
            return [];
        }

        return successResponse(array_merge($setting, [
            'seoData' => $this->pageSeo->forSite($setting),
        ]));
    }

    public function district(): JsonResponse
    {
        return successResponse($this->settingService->districts());
    }

    /**
     * Get areas for a specific district.
     */
    public function area(string $id): JsonResponse
    {
        return successResponse($this->settingService->areasOfDistrict($id));
    }

    public function condition(): JsonResponse
    {
        return successResponse($this->settingService->conditions());
    }

    public function storage(): JsonResponse
    {
        return successResponse($this->settingService->storages());
    }

    public function popupDailog(): JsonResponse
    {
        return successResponse($this->settingService->activePopupDialog());
    }
}
