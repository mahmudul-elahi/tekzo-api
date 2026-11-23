<?php

namespace App\Repositories\Reels;

use App\Enums\ProductStatus;
use App\Enums\StatusEnum;
use App\Models\Reels\ReelsVideo;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class ReelsVideoRepository extends BaseRepository
{
    public function __construct(ReelsVideo $reelsVideo)
    {
        $this->model = $reelsVideo;
    }

    /**
     * Active reels whose product is active, with the product card data.
     *
     * @return Collection<int, ReelsVideo>
     */
    public function activeWithProducts(): Collection
    {
        return $this->query()
            ->whereHas('product', fn ($query) => $query->where('status', ProductStatus::Active->value))
            ->with(['product:id,slug,product_name,headline,product_image,short_description,brand_id', 'product.brand', 'product.offerProduct', 'product.stockVariant' => function ($q) {
                $q->where('status', '1')
                    ->with(['price']);
            }])
            ->where('status', StatusEnum::Active)
            ->select(['id', 'product_id', 'video_link'])
            ->get();
    }
}
