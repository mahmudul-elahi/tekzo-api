<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Customer\VerificationCode;
use App\Services\Sms\ReveSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class GuestPhoneController extends Controller
{
    /**
     * @throws \Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(Request $request, ReveSmsService $smsService): JsonResponse
    {

        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/',
            ],
        ]);

        try {
            DB::beginTransaction();

            $otp = random_int(100000, 999999);

            $production = app()->isProduction();

            VerificationCode::query()->updateOrCreate(
                ['phone_number' => $request->phone_number],
                [
                    'code' => $production ? $otp : 123456, // keeping plain int for SMS
                    'expires_at' => now()->addMinutes(3),
                ]
            );
            $companyName = Config::get('app.name');
            $message = "($companyName) Your {$companyName} OTP is: $otp. Please do not share your OTP with anyone";
            if ($production) {
                $response = $smsService->send($request->phone_number, $message);
                if (! $response['success']) {
                    throw new GeneralException($response['message']);
                }
            }

            DB::commit();

            return responseMessage('Phone update OTP sent successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Phone update request failed' : $e->getMessage(),
                500
            );
        }
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

        $record = VerificationCode::query()
            ->where('phone_number', $request->phone_number)
            ->where('code', $request->code)
            ->where('expires_at', '>=', now())
            ->first();

        if (! $record) {
            throw new GeneralException('Invalid or expired OTP');
        }

        $record->delete();

        return responseMessage('Phone number updated successfully');
    }
}
