<?php

namespace App\Services\Setting;

use App\Models\Area\Area;
use App\Models\NewDistrict\NewDistrict;
use App\Models\PopupDialog\PopupDialog;
use App\Models\Product\AttributeValue;
use App\Repositories\Location\LocationRepository;
use App\Repositories\PopupDialog\PopupDialogRepository;
use App\Repositories\Product\AttributeValueRepository;
use App\Repositories\Settings\SettingRepository;
use App\Repositories\Tenant\TenantRepository;
use App\Services\Shipping\ShippingChargeService;
use Illuminate\Database\Eloquent\Collection;

class SettingService
{
    public function __construct(
        private TenantRepository $tenants,
        private SettingRepository $settings,
        private LocationRepository $locations,
        private AttributeValueRepository $attributeValues,
        private PopupDialogRepository $popupDialogs,
    ) {}

    /**
     * The current tenant's settings as name => value, or null when the tenant doesn't exist.
     *
     * @return array<string, mixed>|null
     */
    public function tenantSettings(): ?array
    {
        $tenant = $this->tenants->current();

        if (! $tenant) {
            return null;
        }

        return $this->settings->getFormattedSettings('tenant', 'App\Models\Tenant\Tenant', $tenant->id);
    }

    /**
     * @return Collection<int, NewDistrict>
     */
    public function districts(): Collection
    {
        return $this->locations->districts();
    }

    /**
     * A district's areas; areas without their own shipping charge get the district default.
     *
     * @return Collection<int, Area>
     */
    public function areasOfDistrict(mixed $districtId): Collection
    {
        $areas = $this->locations->areasOfDistrict($districtId);

        $defaultCharge = ShippingChargeService::resolveDistrictDefault((int) $districtId);

        $areas->each(function ($area) use ($defaultCharge) {
            $area->shipping_charge = $area->shipping_charge !== null
                ? (float) $area->shipping_charge
                : $defaultCharge;
        });

        return $areas;
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    public function conditions(): Collection
    {
        return $this->attributeValues->namesOfAttribute(AttributeValueRepository::CONDITION_ATTRIBUTE_ID);
    }

    /**
     * @return Collection<int, AttributeValue>
     */
    public function storages(): Collection
    {
        return $this->attributeValues->namesOfAttribute(AttributeValueRepository::STORAGE_ATTRIBUTE_ID);
    }

    public function activePopupDialog(): ?PopupDialog
    {
        return $this->popupDialogs->firstActive();
    }
}
