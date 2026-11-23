<?php

namespace App\Repositories\Elite;

use App\Models\Elite\Elite;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class EliteRepository extends BaseRepository
{
    public function __construct(Elite $elite)
    {
        $this->model = $elite;
    }

    /**
     * Elite tiers from the lowest point threshold up.
     *
     * @return Collection<int, Elite>
     */
    public function orderedByPoint(): Collection
    {
        return $this->query()->orderBy('point')->get();
    }
}
