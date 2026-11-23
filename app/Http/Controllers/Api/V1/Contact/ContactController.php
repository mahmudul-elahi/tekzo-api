<?php

namespace App\Http\Controllers\Api\V1\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\ContactRequest;
use App\Services\Contact\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $contactService) {}

    public function store(ContactRequest $request): JsonResponse
    {
        $this->contactService->store($request->validated());

        return successResponse('Message send successfully');
    }
}
