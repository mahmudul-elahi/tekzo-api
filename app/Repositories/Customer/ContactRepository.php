<?php

namespace App\Repositories\Customer;

use App\Models\Contact\Contact;
use App\Repositories\BaseRepository;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
     * Registered customers (those with a password) with this email address or mobile number.
     * A mobile number is matched in every form older records may store it in, and the phone
     * column is not unique, so more than one customer can match.
     *
     * @return Collection<int, Contact>
     */
    public function findRegisteredByEmailOrPhone(string $login): Collection
    {
        return Contact::query()
            ->whereNotNull('password')
            ->when(
                str_contains($login, '@'),
                fn (Builder $query) => $query->where('email', $login),
                fn (Builder $query) => $query->whereIn('phone', PhoneNumber::variants($login)),
            )
            ->orderByDesc('id')
            ->limit(10)
            ->get();
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
