<?php

namespace App\Repositories\Address;

use App\Models\Address\CustomerAddress;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class CustomerAddressRepository extends BaseRepository
{
    public function __construct(CustomerAddress $customerAddress)
    {
        $this->model = $customerAddress;
    }

    /**
     * A customer's addresses, newest first.
     *
     * @param  array<int|string, mixed>  $relations
     * @return Collection<int, CustomerAddress>
     */
    public function forContact(mixed $contactId, array $relations): Collection
    {
        return $this->query()
            ->with($relations)
            ->where('contact_id', $contactId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The customer's address flagged as default for the given column (is_default_shipping / is_default_billing).
     */
    public function findDefault(mixed $contactId, string $column): ?CustomerAddress
    {
        return $this->query()
            ->with(['district:id,name', 'area'])
            ->where('contact_id', $contactId)
            ->where($column, true)
            ->first();
    }

    /**
     * Clear a default flag on the customer's addresses, optionally keeping one address.
     */
    public function clearDefault(mixed $contactId, string $column, ?int $exceptId = null): void
    {
        $this->query()
            ->where('contact_id', $contactId)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update([$column => false]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomerAddress
    {
        return $this->query()->create($attributes);
    }
}
