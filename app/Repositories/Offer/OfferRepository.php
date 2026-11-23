<?php

namespace App\Repositories\Offer;

use App\Models\Offer\Offer;
use App\Repositories\BaseRepository;

class OfferRepository extends BaseRepository
{
    public function __construct(Offer $offer)
    {
        $this->model = $offer;
    }

    public function latestActiveFlashSale(): ?Offer
    {
        return $this->query()
            ->where('is_active', 1)
            ->where('type', 'flash_sale')
            ->latest()
            ->first();
    }
}
