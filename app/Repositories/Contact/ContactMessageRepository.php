<?php

namespace App\Repositories\Contact;

use App\Models\ContactUs\Contactus;
use App\Repositories\BaseRepository;

class ContactMessageRepository extends BaseRepository
{
    public function __construct(Contactus $contactus)
    {
        $this->model = $contactus;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Contactus
    {
        return $this->query()->create($attributes);
    }
}
