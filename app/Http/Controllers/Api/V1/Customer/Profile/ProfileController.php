<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Profile\ChangePasswordRequest;
use App\Http\Requests\Customer\Profile\ProfileRequest;
use App\Http\Resources\Customer\Profile\ProfileResource;
use App\Models\Contact\Contact;
use App\Services\Customer\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(private ProfileService $profileService) {}

    public function index(): JsonResponse
    {
        return successResponse(ProfileResource::make($this->profileService->withEliteDetails($this->authCustomer())));
    }

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function update(ProfileRequest $request): JsonResponse
    {
        $this->profileService->update(
            $this->authCustomer(),
            collect($request->validated())->except('profile_picture')->toArray(),
            $request->hasFile('profile_picture') ? $request->file('profile_picture') : null,
        );

        return successResponse('Profile updated successfully');
    }

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function emailVerifyRequest(Request $request): JsonResponse
    {
        $customer = $this->authCustomer();

        $request->validate([
            'email' => ['required', 'email', 'indisposable', 'unique:contacts,email,'.$customer->id],
        ]);

        $this->profileService->requestEmailChange($customer, $request->email);

        return responseMessage('Email update OTP sent successfully');
    }

    /**
     * @throws GeneralException
     */
    public function emailVerify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required'],
        ]);

        $this->profileService->confirmEmailChange($this->authCustomer(), $request->email, $request->code);

        return responseMessage('Email updated successfully');
    }

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(Request $request): JsonResponse
    {
        $customer = $this->authCustomer();

        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/',
                'unique:contacts,phone,'.$customer->id,
            ],
        ]);

        $this->profileService->requestPhoneChange($customer, $request->phone_number);

        return responseMessage('Phone update OTP sent successfully');
    }

    /**
     * Verify OTP & update phone.
     *
     * @throws GeneralException
     */
    public function phoneVerify(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'code' => ['required'],
        ]);

        $this->profileService->confirmPhoneChange($this->authCustomer(), $request->phone_number, $request->code);

        return responseMessage('Phone number updated successfully');
    }

    /**
     * @throws ValidationException
     */
    public function password(ChangePasswordRequest $request): JsonResponse
    {
        $this->profileService->changePassword($this->authCustomer(), $request->current_password, $request->password);

        return responseMessage('Password updated successfully');
    }

    public function logout(): JsonResponse
    {
        $this->profileService->logout($this->authCustomer());

        return responseMessage('Logout successfully');
    }

    private function authCustomer(): ?Contact
    {
        return auth()->guard('customer')->user();
    }
}
