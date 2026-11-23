<?php

namespace App\Services\Customer;

use App\Exceptions\GeneralException;
use App\Models\Customer\VerificationCode;
use App\Repositories\Customer\VerificationCodeRepository;
use App\Services\Sms\ReveSmsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phone number OTPs. Outside production no SMS is sent and the code is always 123456.
 */
class PhoneOtpService
{
    public function __construct(
        private VerificationCodeRepository $codes,
        private ReveSmsService $smsService,
    ) {}

    /**
     * Store a 3-minute code for the phone number and text it.
     *
     * @throws GeneralException when storing or sending fails
     * @throws Throwable
     */
    public function send(string $phoneNumber): void
    {
        try {
            DB::beginTransaction();

            $otp = random_int(100000, 999999);

            $production = app()->isProduction();

            $this->codes->putForPhone($phoneNumber, $production ? $otp : 123456, now()->addMinutes(3)); // keeping plain int for SMS

            $companyName = Config::get('app.name');
            $message = "($companyName) Your {$companyName} OTP is: $otp. Please do not share your OTP with anyone";

            if ($production) {
                $response = $this->smsService->send($phoneNumber, $message);
                if (! $response['success']) {
                    throw new GeneralException($response['message']);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Phone update request failed' : $e->getMessage(),
                500
            );
        }
    }

    /**
     * The unexpired code record matching the phone number and code, or null.
     */
    public function findValid(string $phoneNumber, mixed $code): ?VerificationCode
    {
        return $this->codes->findUnexpiredForPhone($phoneNumber, $code);
    }
}
