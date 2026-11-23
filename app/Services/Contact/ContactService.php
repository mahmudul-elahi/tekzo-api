<?php

namespace App\Services\Contact;

use App\Repositories\Contact\ContactMessageRepository;

class ContactService
{
    public function __construct(private ContactMessageRepository $messages) {}

    /**
     * @param  array{name: string, email: string, subject: string, message: string}  $message
     */
    public function store(array $message): void
    {
        $this->messages->create($message);
    }
}
