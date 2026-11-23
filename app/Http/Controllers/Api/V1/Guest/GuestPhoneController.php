<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Services\Customer\PhoneOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class GuestPhoneController extends Controller
{
    public function __construct(private PhoneOtpService $phoneOtp) {}

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/',
            ],
        ]);

        $this->phoneOtp->send($request->phone_number);

        return responseMessage('Phone update OTP sent successfully');
    }

    /**
     * @throws GeneralException
     */
    public function phoneVerify(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number' => ['required', 'string'],
            'code' => ['required'],
        ]);

        $record = $this->phoneOtp->findValid($request->phone_number, $request->code);

        if (! $record) {
            throw new GeneralException('Invalid or expired OTP');
        }

        $record->delete();

        return responseMessage('Phone number updated successfully');
    }
}
