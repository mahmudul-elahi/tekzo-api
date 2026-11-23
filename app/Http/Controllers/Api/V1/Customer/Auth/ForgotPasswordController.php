<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\ForgotPasswordRequest;
use App\Http\Requests\Customer\Auth\ResetPasswordRequest;
use App\Http\Requests\Customer\Auth\VerifyOtpRequest;
use App\Services\Customer\CustomerAuthService;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(private readonly CustomerAuthService $authService) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetCode($request->email);

        return responseMessage('OTP sent to your email successfully');
    }

    /**
     * @throws GeneralException
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $this->authService->verifyResetCode($request->email, $request->otp);

        return responseMessage('OTP verified successfully');
    }

    /**
     * @throws GeneralException
     */
    public function confirmPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->email, $request->otp, $request->password);

        return responseMessage('Password reset successfully');
    }
}
