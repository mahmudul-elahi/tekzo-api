<?php

namespace App\Services\Outlet;

use App\Models\Outlet\Outlet;
use App\Repositories\Outlet\OutletRepository;
use Illuminate\Database\Eloquent\Collection;

class OutletService
{
    public function __construct(private OutletRepository $outlets) {}

    /**
     * @return Collection<int, Outlet>
     */
    public function active(): Collection
    {
        return $this->outlets->active();
    }

    public function findActive(string $slug): ?Outlet
    {
        return $this->outlets->findActiveBySlug($slug);
    }
}
