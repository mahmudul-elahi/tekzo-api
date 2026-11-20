<?php

namespace App\Http\Controllers\Customer\Profile;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Profile\ProfileRequest;
use App\Http\Resources\Customer\Profile\ProfileResource;
use App\Mail\Customer\EmailVerifyRequestMail;
use App\Models\Customer\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileController extends Controller
{


    public function index()
    {
        $customer = $this->authCustomer()->load(['elite', 'eliteHistories.elite']);

        return successResponse(ProfileResource::make($customer));
    }

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function update(ProfileRequest $request): \Illuminate\Http\JsonResponse
    {
        $customer = $this->authCustomer();

        try {
            DB::beginTransaction();

            $customer->update(
                collect($request->validated())
                    ->except('profile_picture')
                    ->toArray()
            );

            if ($request->hasFile('profile_picture')) {
                $customer->clearMediaCollection('customer_profile_picture');
                $customer->addMediaFromRequest('profile_picture')
                    ->toMediaCollection('customer_profile_picture');

                $profilePicture = $customer->getCustomerProfilePictureAttribute();

                if ($profilePicture) {
                    $customer->update(['profile_picture' => $profilePicture]);
                }
            }

            DB::commit();
            return successResponse('Profile updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Profile update failed' : $e->getMessage(),
                500
            );
        }
    }

    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function emailVerifyRequest(Request $request): \Illuminate\Http\JsonResponse
    {
        $customer = $this->authCustomer();

        $request->validate([
            'email' => ['required', 'email', 'indisposable', 'unique:contacts,email,' . $customer->id],
        ]);


        if ($customer->email_verified_at && $customer->email === $request->email) {
            throw new GeneralException('Email already verified');
        }

        try {
            DB::beginTransaction();

            $otp = random_int(100000, 999999);

            $production = app()->isProduction();

            VerificationCode::query()->updateOrCreate(
                ['email' => $request->email],
                [
                    'code' => $production ? Hash::make($otp) : Hash::make(123456),
                    'expires_at' => now()->addMinutes(3),
                ]
            );

            if ($production) {
                Mail::to($request->email)->send(new EmailVerifyRequestMail($otp));
            }

            DB::commit();

            return responseMessage('Email update OTP sent successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Email update request failed' : $e->getMessage(),
                500
            );
        }
    }

    public function emailVerify(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required'],
        ]);

        $record = VerificationCode::query()->where('email', $request->email)
            ->where('expires_at', '>=', now())
            ->first();

        if (!$record || !Hash::check($request->code, $record->code)) {
            throw new GeneralException("Invalid your OTP");
        }

        $this->authCustomer()->update([
            'email_verified_at' => now(),
            'email' => $request->email
        ]);
        $record->delete();

        return responseMessage('Email updated successfully');
    }


    /**
     * @throws Throwable
     * @throws GeneralException
     */
    public function phoneVerifyRequest(Request $request): \Illuminate\Http\JsonResponse
    {
        $customer = $this->authCustomer();

        $request->validate([
            'phone_number' => [
                'required',
                'string',
                'regex:/^(\+?88)?01[0-9]{9}$/',
                'unique:contacts,phone,' . $customer->id,
            ],
        ]);

        if ($customer->phone === $request->phone_number) {
            throw new GeneralException('Phone number already verified');
        }

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
     * Verify OTP & update phone
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
            throw new GeneralException("Invalid your OTP");
        }

        $this->authCustomer()->update([
            'phone' => $record->phone_number,
            'phone_verified_at' => now(),

        ]);
        $record->delete();

        return responseMessage('Phone number updated successfully');
    }

    /**
     * Helper function to interact with REVE SMS API.
     */


    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'min:8'],
            'password_confirmation' => ['required_with:password', 'same:password'],
        ]);

        $customer = $this->authCustomer();

        if (!Hash::check($request->current_password, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password was incorrect.',
            ]);
        }

        $customer->update([
            'password' => Hash::make($request->password),
        ]);


        return responseMessage('Password updated successfully');
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authCustomer()->currentAccessToken()->delete();

        return responseMessage('Logout successfully');
    }

    private function authCustomer(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return auth()->guard('customer')->user();
    }

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
