<?php

namespace App\Http\Controllers\Api\V1\Customer\Address;

use App\Http\Controllers\Controller;
use App\Services\Address\AddressService;
use Illuminate\Http\JsonResponse;

class ShippingAddressController extends Controller
{
    public function __construct(private AddressService $addressService) {}

    public function index(): JsonResponse
    {
        return successResponse($this->addressService->shippingAddresses(auth()->guard('customer')->id()));
    }
}
