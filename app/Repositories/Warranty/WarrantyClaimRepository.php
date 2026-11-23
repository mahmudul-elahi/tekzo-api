<?php

namespace App\Repositories\Warranty;

use App\Models\Product\ProductSerial;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleReturnItem;
use App\Models\Warranty\WarrantyClaim;
use App\Repositories\BaseRepository;

class WarrantyClaimRepository extends BaseRepository
{
    public function __construct(WarrantyClaim $warrantyClaim)
    {
        $this->model = $warrantyClaim;
    }

    public function findSerialWithProduct(string $serialNumber): ?ProductSerial
    {
        return ProductSerial::query()
            ->where('serial_number', $serialNumber)
            ->with('product')
            ->first();
    }

    public function existsForSerial(string $serialNumber): bool
    {
        return $this->query()->where('serial_number', $serialNumber)->exists();
    }

    public function serialWasReturned(string $serialNumber): bool
    {
        return SaleReturnItem::query()->where('serial_number', $serialNumber)->exists();
    }

    /**
     * The sale item the serial was sold under, with its sale.
     */
    public function findSaleItemForSerial(string $serialNumber): ?SaleItem
    {
        return SaleItem::query()
            ->where('serial_number', $serialNumber)
            ->with('sale')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): WarrantyClaim
    {
        return WarrantyClaim::create($attributes);
    }
}
