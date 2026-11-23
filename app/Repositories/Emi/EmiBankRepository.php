<?php

namespace App\Repositories\Emi;

use App\Models\Emi\EmiBank;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class EmiBankRepository extends BaseRepository
{
    public function __construct(EmiBank $emiBank)
    {
        $this->model = $emiBank;
    }

    /**
     * Active banks by name, each with its active tenures by months.
     *
     * @return Collection<int, EmiBank>
     */
    public function activeWithTenures(): Collection
    {
        return $this->query()
            ->with(['tenures' => function ($query) {
                $query->where('is_active', 1)->orderBy('months');
            }])
            ->where('is_active', 1)
            ->orderBy('bank_name')
            ->get();
    }
}
