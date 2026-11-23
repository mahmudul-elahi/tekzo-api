<?php

namespace App\Services\Address;

use App\Exceptions\GeneralException;
use App\Models\Address\CustomerAddress;
use App\Repositories\Address\CustomerAddressRepository;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    public function __construct(private readonly CustomerAddressRepository $addresses) {}

    /**
     * @return Collection<int, CustomerAddress>
     */
    public function forCustomer(mixed $contactId): Collection
    {
        return $this->addresses->forContact($contactId, ['district:id,name', 'area', 'contact:id,name,phone']);
    }

    /**
     * Addresses with district and area, for checkout.
     *
     * @return Collection<int, CustomerAddress>
     */
    public function shippingAddresses(mixed $contactId): Collection
    {
        return $this->addresses->forContact($contactId, ['district', 'area']);
    }

    /**
     * Create an address; when it's flagged default, the customer's other addresses lose that flag first.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(int $contactId, array $validated): void
    {
        if (! empty($validated['is_default_shipping'])) {
            $this->addresses->clearDefault($contactId, 'is_default_shipping');
        }

        if (! empty($validated['is_default_billing'])) {
            $this->addresses->clearDefault($contactId, 'is_default_billing');
        }

        $this->addresses->create(array_merge($validated, [
            'contact_id' => $contactId,
        ]));
    }

    /**
     * @throws GeneralException when the address isn't the customer's
     */
    public function ensureOwnedBy(CustomerAddress $address, int $contactId): void
    {
        if ((int) $address->contact_id !== $contactId) {
            throw new GeneralException('Address not found', 404);
        }
    }

    /**
     * Make the address the only default for the column (is_default_shipping / is_default_billing).
     */
    public function makeDefault(CustomerAddress $address, int $contactId, string $column): void
    {
        $this->addresses->clearDefault($contactId, $column, $address->id);

        $address->update([$column => true]);
    }

    /**
     * @return array{shippingAddress: ?CustomerAddress, billingAddress: ?CustomerAddress}
     */
    public function defaults(int $contactId): array
    {
        return [
            'shippingAddress' => $this->addresses->findDefault($contactId, 'is_default_shipping'),
            'billingAddress' => $this->addresses->findDefault($contactId, 'is_default_billing'),
        ];
    }
}
