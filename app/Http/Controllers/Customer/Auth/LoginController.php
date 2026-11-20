<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Contact\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @throws GeneralException
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:contacts,email'],
            'password' => 'required'
        ], [
            'email.exists' => 'No customer account found with this email'
        ]);

        // Find customer by email
        $customer = Contact::query()
            ->where('email', $request->email)
            ->first();

        // Check password
        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect your password']
            ]);
        }

        $customer->update([
            'last_login_at' => now(),
        ]);

        $token = $customer->createToken("customer-{$customer->id}-token")->plainTextToken;

        return successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
