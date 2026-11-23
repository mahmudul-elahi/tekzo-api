<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\GuestPhoneOtpRequest;
use App\Http\Requests\Guest\GuestPhoneVerifyRequest;
use App\Services\Customer\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Throwable;

class GuestPhoneController extends Controller
{
    public function __construct(private PhoneOtpService $phoneOtp) {}

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(GuestPhoneOtpRequest $request): JsonResponse
    {

        $this->phoneOtp->send($request->phone_number);

        return responseMessage('Phone update OTP sent successfully');
    }

    /**
     * @throws GeneralException
     */
    public function phoneVerify(GuestPhoneVerifyRequest $request): JsonResponse
    {

        $record = $this->phoneOtp->findValid($request->phone_number, $request->code);

        if (! $record) {
            throw new GeneralException('Invalid or expired OTP');
        }

        $record->delete();

        return responseMessage('Phone number updated successfully');
    }
}
