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
     * A guest contact (no password) with exactly this email and phone.
     */
    public function findGuest(mixed $email, mixed $phone): ?Contact
    {
        return $this->query()
            ->where('email', $email)
            ->where('phone', $phone)
            ->whereNull('password')
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
