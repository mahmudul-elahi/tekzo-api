<?php

namespace App\Repositories\OurTeam;

use App\Models\OurTeam\OurTeam;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class OurTeamRepository extends BaseRepository
{
    public function __construct(OurTeam $ourTeam)
    {
        $this->model = $ourTeam;
    }

    /**
     * @return Collection<int, OurTeam>
     */
    public function members(): Collection
    {
        return $this->query()
            ->select('id', 'name', 'about', 'designation', 'profile_photo')
            ->get();
    }
}
