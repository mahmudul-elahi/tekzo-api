<?php

namespace App\Services\Shipping;

use App\Models\Area\Area;
use App\Models\NewDistrict\NewDistrict;
use Illuminate\Support\Facades\DB;

/**
 * Shipping charge resolution order:
 * 1. The area's own `shipping_charge` (set via the admin panel's Area
 *    Shipping Charges page) — NULL means "not configured", 0 means an
 *    explicit free-delivery override, both distinguished here.
 * 2. Falls back to the district-level Delivery Charge Settings
 *    (Salamat-Innovation-POS), stored in the shared `customizations`
 *    table (type=delivery_charge) both apps' DBs point at.
 * 3. Falls back further to ৳70 inside Dhaka / ৳120 outside if that was
 *    never configured either.
 */
class ShippingChargeService
{
    private const DEFAULT_SHIPPING_CHARGE_INSIDE_DHAKA = 70.0;
    private const DEFAULT_SHIPPING_CHARGE_OUTSIDE_DHAKA = 120.0;

    public static function resolveForArea(int $districtId, int $areaId): float
    {
        $areaShippingCharge = Area::query()
            ->where('id', $areaId)
            ->value('shipping_charge');

        // Same gap as resolveDistrictDefault() below: `!== null` alone treats an
        // empty string from a blanked-out admin field as "configured", and
        // (float) '' silently becomes 0. A genuine numeric 0 is still honored as
        // an intentional free-delivery override — only non-numeric garbage falls
        // through to the district default instead of charging nothing by accident.
        if ($areaShippingCharge !== null && $areaShippingCharge !== '' && is_numeric($areaShippingCharge)) {
            return (float) $areaShippingCharge;
        }

        return self::resolveDistrictDefault($districtId);
    }

    public static function resolveDistrictDefault(int $districtId): float
    {
        $isDhaka = NewDistrict::query()
            ->where('id', $districtId)
            ->whereRaw('LOWER(name) = ?', ['dhaka'])
            ->exists();

        $settings = DB::table('customizations')
            ->where('type', 'delivery_charge')
            ->pluck('value', 'name');

        $key = $isDhaka ? 'inside_dhaka_charge' : 'outside_dhaka_charge';
        $default = $isDhaka ? self::DEFAULT_SHIPPING_CHARGE_INSIDE_DHAKA : self::DEFAULT_SHIPPING_CHARGE_OUTSIDE_DHAKA;

        // `??` alone only catches a missing/NULL setting — an empty string saved from
        // a blanked-out admin form field passes it right through, and (float) '' is
        // silently 0 with no error. Treat anything that isn't a real positive number
        // as "not configured" too, so a bad save falls back to the default instead of
        // quietly charging nothing.
        $value = $settings[$key] ?? null;

        if ($value === null || $value === '' || !is_numeric($value) || (float) $value <= 0) {
            return $default;
        }

        return (float) $value;
    }
}
