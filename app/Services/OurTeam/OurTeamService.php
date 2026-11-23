<?php

namespace App\Services\OurTeam;

use App\Models\OurTeam\OurTeam;
use App\Repositories\OurTeam\OurTeamRepository;
use Illuminate\Database\Eloquent\Collection;

class OurTeamService
{
    public function __construct(private OurTeamRepository $members) {}

    /**
     * @return Collection<int, OurTeam>
     */
    public function members(): Collection
    {
        return $this->members->members();
    }
}
