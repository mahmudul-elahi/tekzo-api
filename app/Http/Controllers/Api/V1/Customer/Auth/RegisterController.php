<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\RegisterRequest;
use App\Models\Contact\Contact;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{

    public function register(RegisterRequest $request)
    {
        $customer = Contact::query()
            ->create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'status' => 'active',
                'type' => 'consumer',
                'online' => 1
            ]);

        // Create Sanctum token
        $token = $customer->createToken("customer-{$customer->id}-token")->plainTextToken;

        return successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
