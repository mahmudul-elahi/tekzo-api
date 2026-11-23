<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\OurTeam;

use App\Http\Controllers\Controller;
use App\Services\OurTeam\OurTeamService;
use Illuminate\Database\Eloquent\Collection;

class OurTeamController extends Controller
{
    public function __construct(private readonly OurTeamService $ourTeamService) {}

    /**
     * Returned without the usual "data" wrapper, as the frontend expects.
     */
    public function index(): Collection
    {
        return $this->ourTeamService->members();
    }
}
