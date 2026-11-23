<?php

namespace App\Services\Offer;

use App\Models\Offer\Offer;
use App\Repositories\Offer\OfferRepository;
use App\Repositories\Product\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OfferService
{
    public function __construct(
        private OfferRepository $offers,
        private ProductRepository $products,
    ) {}

    public function paginateActive(int $perPage): LengthAwarePaginator
    {
        return $this->offers->paginateActiveLatestFirst($perPage);
    }

    /**
     * An active offer and a page of its active products, or null when the offer doesn't exist.
     *
     * @return array{offer: Offer, products: LengthAwarePaginator}|null
     */
    public function offerWithProducts(string $slug, int $perPage): ?array
    {
        $offer = $this->offers->findActiveBySlug($slug);

        if (! $offer) {
            return null;
        }

        return [
            'offer' => $offer,
            'products' => $this->products->paginateActiveInOffer($offer->id, $perPage),
        ];
    }
}
