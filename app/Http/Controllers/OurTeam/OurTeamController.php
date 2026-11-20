<?php

namespace App\Http\Controllers\OurTeam;

use App\Http\Controllers\Controller;
use App\Models\OurTeam\OurTeam;

class OurTeamController extends Controller
{
    public function index()
    {
        return OurTeam::query()
            ->select('id', 'name', 'about', 'designation', 'profile_photo')
            ->get();
    }
}
