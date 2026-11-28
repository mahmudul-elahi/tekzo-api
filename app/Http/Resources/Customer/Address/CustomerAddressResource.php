<?php

declare(strict_types=1);

namespace App\Http\Resources\Customer\Address;

use App\Services\Shipping\ShippingChargeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $district = $this->whenLoaded('district');
        $area = $this->whenLoaded('area');

        return [
            'id' => $this->id,
            'contact_id' => $this->contact_id,
            'street' => $this->street,
            'recipient_name' => $this->resource->recipient_name,
            'recipient_phone' => $this->resource->recipient_phone,
            'label' => $this->label,
            'is_default_shipping' => $this->is_default_shipping,
            'is_default_billing' => $this->is_default_billing,
            'district_id' => $this->new_district_id,
            'area_id' => $this->area_id,
            'area_detail' => $district && $area
                ? "{$district->name}-{$area->name}-{$area->thana}-{$area->postcode}"
                : null,

            'area' => $area ? [
                'thana' => $area->thana,
                'postcode' => $area->postcode,
            ] : null,

            'shipping_charge' => $this->new_district_id && $this->area_id
                ? ShippingChargeService::resolveForArea((int) $this->new_district_id, (int) $this->area_id)
                : null,

            'contact' => $this->contact,
        ];
    }
}
