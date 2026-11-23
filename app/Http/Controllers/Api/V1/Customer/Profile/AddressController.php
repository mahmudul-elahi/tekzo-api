<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\AddressRequest;
use App\Http\Resources\Customer\Address\CustomerAddressResource;
use App\Models\Address\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    protected function customerId(): int
    {
        return (int) auth()->guard('customer')->id();
    }

    public function index(): JsonResponse
    {
        $addresses = CustomerAddress::query()
            ->with(['district:id,name', 'area', 'contact:id,name,phone'])
            ->where('contact_id', $this->customerId())
            ->orderByDesc('id')
            ->get();

        return successResponse(CustomerAddressResource::collection($addresses));
    }

    /**
     * Store a new customer address
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $customerId = $this->customerId();

        // Handle default shipping
        if (! empty($validated['is_default_shipping'])) {
            CustomerAddress::query()
                ->where('contact_id', $customerId)
                ->update(['is_default_shipping' => false]);
        }

        // Handle default billing
        if (! empty($validated['is_default_billing'])) {
            CustomerAddress::query()
                ->where('contact_id', $customerId)
                ->update(['is_default_billing' => false]);
        }

        // Create the new address
        CustomerAddress::query()->create(array_merge($validated, [
            'contact_id' => $customerId,
        ]));

        return responseMessage('Address created successfully');
    }

    /**
     * Show an existing address
     */
    public function show(CustomerAddress $address): JsonResponse
    {
        if ((int) $address->contact_id !== $this->customerId()) {
            return responseMessage('Address not found', 404);
        }

        return successResponse($address);
    }

    /**
     * Update an existing address
     */
    public function update(AddressRequest $request, CustomerAddress $address): JsonResponse
    {
        if ((int) $address->contact_id !== $this->customerId()) {
            return responseMessage('Address not found', 404);
        }

        $validated = $request->validated();

        $address->update($validated);

        return responseMessage('Address updated successfully');
    }

    /**
     * Delete an existing address
     */
    public function destroy(CustomerAddress $address): JsonResponse
    {
        if ((int) $address->contact_id !== $this->customerId()) {
            return responseMessage('Address not found', 404);
        }

        $address->delete();

        return responseMessage('Address deleted successfully');
    }

    public function defaultAddress(Request $request, CustomerAddress $address): JsonResponse
    {
        if ((int) $address->contact_id !== $this->customerId()) {
            return responseMessage('Address not found', 404);
        }

        $validated = $request->validate([
            'is_default_shipping' => ['nullable', 'boolean'],
            'is_default_billing' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default_shipping'])) {
            $this->setDefault($address, 'is_default_shipping');

            return responseMessage('Default shipping address updated successfully');
        }

        if (! empty($validated['is_default_billing'])) {
            $this->setDefault($address, 'is_default_billing');

            return responseMessage('Default billing address updated successfully');
        }

        return responseMessage('No default address updated');
    }

    public function defaultShippingBillingAddress(): JsonResponse
    {
        $customerId = $this->customerId();

        $shippingAddress = CustomerAddress::query()
            ->with(['district:id,name', 'area'])
            ->where('contact_id', $customerId)
            ->where('is_default_shipping', true)
            ->first();

        $billingAddress = CustomerAddress::query()
            ->with(['district:id,name', 'area'])
            ->where('contact_id', $customerId)
            ->where('is_default_billing', true)
            ->first();

        return successResponse([
            'shippingAddress' => $shippingAddress,
            'billingAddress' => $billingAddress,
        ]);
    }

    /**
     * Helper to set the given address as default for a specific type.
     */
    protected function setDefault(CustomerAddress $address, string $column): void
    {
        CustomerAddress::query()
            ->where('contact_id', $this->customerId())
            ->where('id', '!=', $address->id)
            ->update([$column => false]);

        $address->update([$column => true]);
    }
}
