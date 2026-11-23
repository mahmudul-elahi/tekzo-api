<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Address\AddressRequest;
use App\Http\Resources\Customer\Address\CustomerAddressResource;
use App\Models\Address\CustomerAddress;
use App\Services\Address\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addressService) {}

    public function index(): JsonResponse
    {
        return successResponse(CustomerAddressResource::collection($this->addressService->forCustomer($this->customerId())));
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $this->addressService->create($this->customerId(), $request->validated());

        return responseMessage('Address created successfully');
    }

    /**
     * @throws GeneralException
     */
    public function show(CustomerAddress $address): JsonResponse
    {
        $this->addressService->ensureOwnedBy($address, $this->customerId());

        return successResponse($address);
    }

    /**
     * @throws GeneralException
     */
    public function update(AddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $this->addressService->ensureOwnedBy($address, $this->customerId());

        $address->update($request->validated());

        return responseMessage('Address updated successfully');
    }

    /**
     * @throws GeneralException
     */
    public function destroy(CustomerAddress $address): JsonResponse
    {
        $this->addressService->ensureOwnedBy($address, $this->customerId());

        $address->delete();

        return responseMessage('Address deleted successfully');
    }

    /**
     * Make the address the default shipping (or, failing that, billing) address.
     *
     * @throws GeneralException
     */
    public function defaultAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->addressService->ensureOwnedBy($address, $this->customerId());

        $validated = $request->validate([
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default_shipping'])) {
            $this->addressService->makeDefault($address, $this->customerId(), 'is_default_shipping');

            return responseMessage('Default shipping address updated successfully');
        }

        if (! empty($validated['is_default_billing'])) {
            $this->addressService->makeDefault($address, $this->customerId(), 'is_default_billing');

            return responseMessage('Default billing address updated successfully');
        }

        return responseMessage('No default address updated');
    }

    public function defaultShippingBillingAddress(): JsonResponse
    {
        return successResponse($this->addressService->defaults($this->customerId()));
    }

    private function customerId(): int
    {
        return (int) auth()->guard('customer')->id();
    }
}
