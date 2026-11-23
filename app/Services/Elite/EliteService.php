<?php

declare(strict_types=1);

namespace App\Services\Elite;

use App\Models\Elite\Elite;
use App\Repositories\Elite\EliteRepository;
use Illuminate\Database\Eloquent\Collection;

class EliteService
{
    public function __construct(private readonly EliteRepository $elites) {}

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
