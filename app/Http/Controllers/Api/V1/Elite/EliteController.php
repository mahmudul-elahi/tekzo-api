<?php

namespace App\Http\Controllers\Api\V1\Elite;

use App\Http\Controllers\Controller;
use App\Http\Resources\Elite\EliteResource;
use App\Models\Elite\Elite;

class EliteController extends Controller
{
    public function index()
    {
        $elites          = Elite::orderBy('point')->get();
        $customerPoints  = auth('sanctum')->user()?->total_points;

        $data = $elites->map(fn($elite) => new EliteResource($elite, $customerPoints));

        return successResponse($data);
    }
}
