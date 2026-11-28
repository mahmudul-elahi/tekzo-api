<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Enums\SocialProvider;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\SocialLoginRequest;
use App\Services\Customer\Social\SocialAuthService;
use Illuminate\Http\JsonResponse;

class SocialLoginController extends Controller
{
    public function __construct(private readonly SocialAuthService $socialAuth) {}

    /**
     * @throws GeneralException
     */
    public function login(SocialLoginRequest $request, SocialProvider $provider): JsonResponse
    {
        return successResponse([
            'access_token' => $this->socialAuth->login($provider, $request->string('token')->toString(), $request->validated('name')),
            'token_type' => 'Bearer',
        ]);
    }
}
