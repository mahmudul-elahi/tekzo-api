<?php

namespace App\Repositories\Offer;

use App\Models\Offer\Offer;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OfferRepository extends BaseRepository
{
    public function __construct(Offer $offer)
    {
        $this->model = $offer;
    }

    public function paginateActiveLatestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->orderByDesc('id')
            ->where('is_active', 1)
            ->paginate($perPage);
    }

    public function findActiveBySlug(string $slug): ?Offer
    {
        return $this->query()
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->select('id', 'slug', 'title', 'description', 'banner', 'start_at', 'end_at')
            ->first();
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
