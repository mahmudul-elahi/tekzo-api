<?php

namespace App\Http\Controllers\Gust;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Customer\VerificationCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GustPhoneController extends Controller
{
    /**
     * @throws \Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(Request $request): \Illuminate\Http\JsonResponse
    {

        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/'
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
                $response = $this->sendSms($request->phone_number, $message);
                if (!$response['success']) {
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
    public function phoneVerify(Request $request): \Illuminate\Http\JsonResponse
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


        if (!$record) {
            throw new GeneralException("Invalid or expired OTP");
        }

        $record->delete();


        return responseMessage('Phone number updated successfully');
    }

    /**
     * @throws ConnectionException
     */
    private function sendSms(string $phoneNumber, string $messageContent): array
    {
        $apiKey = Config::get('services.reve_sms.api_key');
        $secret = Config::get('services.reve_sms.secret_key');
        $sender = Config::get('services.reve_sms.sender_id');
        $endpoint = Config::get('services.reve_sms.endpoint');

        $response = Http::get($endpoint, [
            'apikey' => $apiKey,
            'secretkey' => $secret,
            'callerID' => $sender,
            'toUser' => $phoneNumber,
            'messageContent' => $messageContent
        ]);

        $data = $response->json();

        if (isset($data['Status']) && $data['Status'] == "0") {
            return ['success' => true, 'message_id' => $data['Message_ID']];
        }

        $errorMessage = match ($data['Status'] ?? null) {
            '109' => 'User not provided/deleted or API key is missing.',
            '108' => 'Wrong password or not provided.',
            '114' => 'Content not provided.',
            '101' => 'Internal server error.',
            '1' => 'Request failed.',
            default => 'An unknown error occurred.'
        };

        return ['success' => false, 'message' => $errorMessage];
    }
}
