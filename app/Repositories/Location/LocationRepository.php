<?php

namespace App\Repositories\Location;

use App\Models\Area\Area;
use App\Models\NewDistrict\NewDistrict;
use Illuminate\Database\Eloquent\Collection;

class LocationRepository
{
    /**
     * @return Collection<int, NewDistrict>
     */
    public function districts(): Collection
    {
        return NewDistrict::query()
            ->select(['id', 'name'])
            ->get();
    }

    /**
     * @return Collection<int, Area>
     */
    public function areasOfDistrict(mixed $districtId): Collection
    {
        return Area::query()
            ->where('new_district_id', $districtId)
            ->select(['id', 'name', 'new_district_id', 'thana', 'postcode', 'shipping_charge'])
            ->get();
    }
}
