<?php

namespace App\Services\Elite;

use App\Models\Elite\Elite;
use App\Repositories\Elite\EliteRepository;
use Illuminate\Database\Eloquent\Collection;

class EliteService
{
    public function __construct(private EliteRepository $elites) {}

    /**
     * Elite tiers from the lowest point threshold up.
     *
     * @return Collection<int, Elite>
     */
    public function tiers(): Collection
    {
        return $this->elites->orderedByPoint();
    }
}
