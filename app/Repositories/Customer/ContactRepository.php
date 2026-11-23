<?php

namespace App\Repositories\Customer;

use App\Models\Contact\Contact;
use App\Repositories\BaseRepository;

/**
 * Customers are stored as contacts.
 */
class ContactRepository extends BaseRepository
{
    public function __construct(Contact $contact)
    {
        $this->model = $contact;
    }

    public function findByEmail(string $email): ?Contact
    {
        return $this->query()
            ->where('email', $email)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Contact
    {
        return $this->query()->create($attributes);
    }
}
