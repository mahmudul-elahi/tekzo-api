<?php

namespace App\Services\Customer;

use App\Exceptions\GeneralException;
use App\Mail\Customer\EmailVerifyRequestMail;
use App\Models\Contact\Contact;
use App\Repositories\Customer\VerificationCodeRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfileService
{
    public function __construct(
        private VerificationCodeRepository $codes,
        private PhoneOtpService $phoneOtp,
    ) {}

    public function withEliteDetails(Contact $customer): Contact
    {
        return $customer->load(['elite', 'eliteHistories.elite']);
    }

    /**
     * Update profile fields and, when given, replace the profile picture.
     *
     * @param  array<string, mixed>  $fields
     *
     * @throws GeneralException when the update fails
     * @throws Throwable
     */
    public function update(Contact $customer, array $fields, ?UploadedFile $profilePicture): void
    {
        try {
            DB::beginTransaction();

            $customer->update($fields);

            if ($profilePicture) {
                $customer->clearMediaCollection('customer_profile_picture');
                $customer->addMediaFromRequest('profile_picture')
                    ->toMediaCollection('customer_profile_picture');

                $profilePictureUrl = $customer->getCustomerProfilePictureAttribute();

                if ($profilePictureUrl) {
                    $customer->update(['profile_picture' => $profilePictureUrl]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Profile update failed' : $e->getMessage(),
                500
            );
        }
    }

    /**
     * Store a 3-minute code for a new email; the code is mailed only in production (123456 elsewhere).
     *
     * @throws GeneralException when the email is already the verified one, or storing fails
     * @throws Throwable
     */
    public function requestEmailChange(Contact $customer, string $email): void
    {
        if ($customer->email_verified_at && $customer->email === $email) {
            throw new GeneralException('Email already verified');
        }

        try {
            DB::beginTransaction();

            $otp = random_int(100000, 999999);

            $production = app()->isProduction();

            $this->codes->putForEmail($email, $production ? Hash::make($otp) : Hash::make(123456), now()->addMinutes(3));

            if ($production) {
                Mail::to($email)->send(new EmailVerifyRequestMail($otp));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(
                app()->isProduction() ? 'Email update request failed' : $e->getMessage(),
                500
            );
        }
    }

    /**
     * Switch to the new, now verified email when the code matches.
     *
     * @throws GeneralException when the code is wrong or expired
     */
    public function confirmEmailChange(Contact $customer, string $email, mixed $code): void
    {
        $record = $this->codes->findUnexpiredForEmail($email);

        if (! $record || ! Hash::check($code, $record->code)) {
            throw new GeneralException('Invalid your OTP');
        }

        $customer->update([
            'email_verified_at' => now(),
            'email' => $email,
        ]);
        $record->delete();
    }

    /**
     * @throws GeneralException when it's already the customer's number, or sending fails
     * @throws Throwable
     */
    public function requestPhoneChange(Contact $customer, string $phoneNumber): void
    {
        if ($customer->phone === $phoneNumber) {
            throw new GeneralException('Phone number already verified');
        }

        $this->phoneOtp->send($phoneNumber);
    }

    /**
     * Switch to the new, now verified phone number when the code matches.
     *
     * @throws GeneralException when the code is wrong or expired
     */
    public function confirmPhoneChange(Contact $customer, string $phoneNumber, mixed $code): void
    {
        $record = $this->phoneOtp->findValid($phoneNumber, $code);

        if (! $record) {
            throw new GeneralException('Invalid your OTP');
        }

        $customer->update([
            'phone' => $record->phone_number,
            'phone_verified_at' => now(),

        ]);
        $record->delete();
    }

    /**
     * Change the password and sign out every other session (the current token stays).
     *
     * @throws ValidationException when the current password is wrong
     */
    public function changePassword(Contact $customer, string $currentPassword, string $password): void
    {
        if (! Hash::check($currentPassword, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The provided password was incorrect.',
            ]);
        }

        $customer->update([
            'password' => Hash::make($password),
        ]);

        $currentTokenId = $customer->currentAccessToken()?->id;
        $customer->tokens()->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))->delete();
    }

    public function logout(Contact $customer): void
    {
        $customer->currentAccessToken()->delete();
    }
}
