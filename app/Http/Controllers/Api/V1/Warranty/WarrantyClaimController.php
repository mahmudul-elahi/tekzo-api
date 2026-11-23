<?php

namespace App\Http\Controllers\Api\V1\Warranty;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Warranty\WarrantyClaimRequest;
use App\Services\Warranty\WarrantyClaimService;
use Illuminate\Http\JsonResponse;

class WarrantyClaimController extends Controller
{
    public function __construct(private WarrantyClaimService $warrantyClaimService) {}

    /**
     * Public endpoint: a customer enters their IMEI/SL number + a reason and
     * this both verifies the serial is real and logs the claim in one step.
     *
     * @throws GeneralException
     */
    public function store(WarrantyClaimRequest $request): JsonResponse
    {
        $claim = $this->warrantyClaimService->submit($request->serial_number, $request->reason);

        return responseMessage('Your warranty claim has been submitted successfully.', 201, $claim);
    }
}
