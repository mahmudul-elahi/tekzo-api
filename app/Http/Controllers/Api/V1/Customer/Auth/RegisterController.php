<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\RegisterRequest;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private CustomerAuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return successResponse([
            'access_token' => $this->authService->register($request->name, $request->email, $request->phone, $request->password),
            'token_type' => 'Bearer',
        ]);
    }
}
