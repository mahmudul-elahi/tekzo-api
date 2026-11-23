<?php

namespace App\Repositories\Outlet;

use App\Models\Outlet\Outlet;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OutletRepository extends BaseRepository
{
    public function __construct(Outlet $outlet)
    {
        $this->model = $outlet;
    }

    /**
     * @return Collection<int, Outlet>
     */
    public function active(): Collection
    {
        return $this->withSortedImages()
            ->where('status', 1)
            ->get();
    }

    public function findActiveBySlug(string $slug): ?Outlet
    {
        return $this->withSortedImages()
            ->where('slug', $slug)
            ->where('status', 1)
            ->first();
    }

    private function withSortedImages(): Builder
    {
        return $this->query()
            ->with(['images' => function ($query) {
                $query->select('id', 'image_url', 'alt_text', 'outlet_id')->orderBy('sort_order', 'asc');
            }]);
    }
}
