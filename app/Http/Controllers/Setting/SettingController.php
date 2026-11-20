<?php

namespace App\Http\Controllers\Setting;

use App\Enums\StatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Area\Area;
use App\Models\NewDistrict\NewDistrict;
use App\Models\Page\Page;
use App\Models\PopupDialog\PopupDialog;
use App\Models\Product\AttributeValue;
use App\Models\Tenant\Tenant;
use App\Repositories\Settings\SettingRepository;
use App\Services\Shipping\ShippingChargeService;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function index()
    {
        $tenant = Tenant::query()
            ->where('slug', config('services.tenant_slug'))
            ->first();

        if (!$tenant) {
            return [];
        }
        $setting =  resolve(SettingRepository::class)
            ->getFormattedSettings('tenant', 'App\Models\Tenant\Tenant', $tenant->id);

        return successResponse(array_merge($setting, [
            'seoData' => $this->getPageWithSeo($setting),
        ]));
    }

    private function getPageWithSeo($setting)
    {
        $page = Page::query()
            ->whereHas('tenant', fn($query) => $query->where('slug', config('services.tenant_slug')))
            ->where('slug', 'home')
            ->select(['id', 'slug', 'primary_logo', 'tenant_id', 'description'])
            ->first();
        if (!$page) {
            return null;
        }

        $seo = DB::table('seo')
            ->where('model_id', $page->id)
            ->where('model_type', 'App\Models\Pos\Page\Page')
            ->where('tenant_id', $page->tenant_id)
            ->select(['id', 'title', 'description', 'tags'])
            ->first();

        if (!$seo) {
            return null;
        }

        $socialLinks = array_values(array_filter([
            $setting['facebook_link'] ?? null,
            $setting['instagram_link'] ?? null,
            $setting['youtube_link'] ?? null,
            $setting['linkedin_link'] ?? null,
        ]));

        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'tags' => $seo->tags,
            'canonical' => config('services.front_base_url'),
            'ogImage' => $setting['company_logo'] ?? $page->primary_logo ?? null,
            'siteName' => config('app.name'),
            'long_description' => $page->description,
            'foundingDate' => $setting['founding_date'] ?? '2025',
            'streetAddress' => $setting['primary_address'] ?? '',
            'socialLinks' => $socialLinks,

        ];
    }


    public function district()
    {
        $districts = NewDistrict::query()
            //            ->filters(new NameFilter())
            ->select(['id', 'name'])
            ->get();

        return successResponse($districts);
    }

    /**
     * Get areas for a specific district
     */
    public function area($id)
    {
        $areas = Area::query()
            //            ->filters(new NameFilter())
            ->where('new_district_id', $id)
            ->select(['id', 'name', 'new_district_id', 'thana', 'postcode', 'shipping_charge'])
            ->get();

        $defaultCharge = ShippingChargeService::resolveDistrictDefault((int) $id);

        $areas->each(function ($area) use ($defaultCharge) {
            $area->shipping_charge = $area->shipping_charge !== null
                ? (float) $area->shipping_charge
                : $defaultCharge;
        });

        return successResponse($areas);
    }

    public function condition()
    {
        $conditions = AttributeValue::query()
            ->where('attribute_id', 4)
            ->select(['id', 'value as name'])
            ->get();

        return successResponse($conditions);
    }

    public function storage()
    {
        $storages = AttributeValue::query()
            ->where('attribute_id', 3)
            ->select(['id', 'value as name'])
            ->get();

        return successResponse($storages);
    }

    public function popupDailog()
    {

        $popupDialog = PopupDialog::query()
            ->where('status', StatusEnum::Active->value)
            ->select(['id', 'name', 'image', 'mobile_image', 'url'])
            ->first();

        return successResponse($popupDialog);
    }
}
