<?php

namespace App\Http\Controllers\Api\V1\Contact;

use App\Http\Controllers\Controller;
use App\Models\ContactUs\Contactus;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'max:100'],
            'email' => ['required', 'max:100', 'indisposable'],
            'subject' => ['required', 'max:190'],
            'message' => ['required', 'string', 'max:10000']
        ]);

        Contactus::query()->create($validated);

        return successResponse('Message send successfully');
    }
}
