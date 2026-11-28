<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Enums\SocialProvider;
use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Profile\SocialAccountRequest;
use App\Models\Contact\Contact;
use App\Models\Contact\ContactSocialAccount;
use App\Services\Customer\Social\SocialAuthService;
use Illuminate\Http\JsonResponse;

/**
 * The Google, Facebook and Apple accounts the signed-in customer can log in with.
 */
class SocialAccountController extends Controller
{
    public function __construct(private readonly SocialAuthService $socialAuth) {}

    public function index(): JsonResponse
    {
        $connected = $this->authCustomer()->socialAccounts()->get()->keyBy(
            fn (ContactSocialAccount $account): string => $account->provider->value,
        );

        return successResponse(collect(SocialProvider::cases())->map(fn (SocialProvider $provider): array => [
            'provider' => $provider->value,
            'connected' => $connected->has($provider->value),
            'email' => $connected->get($provider->value)?->email,
            'connected_at' => $connected->get($provider->value)?->created_at?->toIso8601String(),
        ])->values());
    }

    /**
     * @throws GeneralException
     */
    public function store(SocialAccountRequest $request, SocialProvider $provider): JsonResponse
    {
        $this->socialAuth->connect($this->authCustomer(), $provider, $request->string('token')->toString());

        return responseMessage(ucfirst($provider->value).' account connected');
    }

    public function destroy(SocialProvider $provider): JsonResponse
    {
        $this->socialAuth->disconnect($this->authCustomer(), $provider);

        return responseMessage(ucfirst($provider->value).' account disconnected');
    }

    private function authCustomer(): Contact
    {
        /** @var Contact */
        return auth()->guard('customer')->user();
    }
}
