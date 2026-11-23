<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Address\CustomerAddress;
use App\Models\Contact\Contact;

class CustomerAddressPolicy
{
    public function view(Contact $contact, CustomerAddress $address): bool
    {
        return (int) $address->contact_id === (int) $contact->id;
    }

    public function update(Contact $contact, CustomerAddress $address): bool
    {
        return (int) $address->contact_id === (int) $contact->id;
    }

    public function delete(Contact $contact, CustomerAddress $address): bool
    {
        return (int) $address->contact_id === (int) $contact->id;
    }
}
