<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\LoginRequest;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private readonly CustomerAuthService $authService) {}

    /**
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return successResponse([
            'access_token' => $this->authService->login($request->email, $request->password),
            'token_type' => 'Bearer',
        ]);
    }
}
