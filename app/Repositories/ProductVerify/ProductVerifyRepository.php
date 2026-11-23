<?php

namespace App\Repositories\ProductVerify;

use App\Models\ProductVerify\ProductVerify;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ProductVerifyRepository extends BaseRepository
{
    public function __construct(ProductVerify $productVerify)
    {
        $this->model = $productVerify;
    }

    /**
     * Verified products registered to a phone number.
     *
     * @return Collection<int, ProductVerify>
     */
    public function forPhone(string $phone): Collection
    {
        return $this->query()
            ->select('id', 'phone', 'name', 'barcode_number', 'product_id', 'created_at', 'status', 'claimed')
            ->where('phone', $phone)
            ->with(['product:id,product_name,product_image'])
            ->get();
    }
}
