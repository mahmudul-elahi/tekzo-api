<?php

namespace App\Repositories\Product;

use App\Models\Product\ProductVariant;
use App\Repositories\BaseRepository;

class ProductVariantRepository extends BaseRepository
{
    public function __construct(ProductVariant $productVariant)
    {
        $this->model = $productVariant;
    }

    public function findById(mixed $id): ?ProductVariant
    {
        return $this->query()->find($id);
    }

    /**
     * A variant with its price, offer and product.
     *
     * @param  array<int, string>  $relations
     */
    public function findWithPricing(mixed $id, array $relations = ['price', 'offerProduct']): ?ProductVariant
    {
        return ProductVariant::with($relations)->find($id);
    }

    /**
     * A variant with its price, offer and product, row-locked for the current transaction.
     */
    public function findWithPricingForUpdate(mixed $id): ?ProductVariant
    {
        return $this->query()
            ->with(['price', 'offerProduct', 'product'])
            ->lockForUpdate()
            ->find($id);
    }
}
