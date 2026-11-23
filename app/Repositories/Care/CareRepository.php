<?php

namespace App\Repositories\Care;

use App\Models\Care\PreOwnedCare;
use App\Models\Care\ProductCare;

/**
 * Care (protection) plans attached to products and pre-owned units.
 */
class CareRepository
{
    public function findProductCare(mixed $id): ?ProductCare
    {
        return ProductCare::with('care')
            ->find($id);
    }

    public function findPreOwnedCare(mixed $id): ?PreOwnedCare
    {
        return PreOwnedCare::with('care')
            ->find($id);
    }
}
