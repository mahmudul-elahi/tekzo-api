<?php

namespace App\Services\Customer;

use App\Exceptions\GeneralException;
use App\Mail\Customer\EmailVerifyRequestMail;
use App\Models\Contact\Contact;
use App\Models\Customer\VerificationCode;
use App\Repositories\Customer\ContactRepository;
use App\Repositories\Customer\VerificationCodeRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
{
    /** Failed OTP attempts before the code is discarded. */
    private const int MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly VerificationCodeRepository $codes,
    ) {}

    /**
     * Check the credentials, record the login, and issue a Sanctum token.
     *
     * @param  string  $login  an email address, or a mobile number in +8801XXXXXXXXX form
     *
     * @throws ValidationException when no registered customer matches the login and password
     */
    public function login(string $login, string $password): string
    {
        $customer = $this->contacts->findRegisteredByEmailOrPhone($login)
            ->first(fn (Contact $candidate): bool => Hash::check($password, $candidate->password));

        if (! $customer instanceof Contact) {
            throw ValidationException::withMessages([
                'password' => ['The email/mobile number or password is incorrect.'],
            ]);
        }

        $customer->update([
            'last_login_at' => now(),
        ]);

        return $customer->createToken("customer-{$customer->id}-token")->plainTextToken;
    }

    /**
     * Create an active consumer account and issue a Sanctum token.
     */
    public function register(string $name, string $email, string $phone, string $password): string
    {
        $customer = $this->contacts->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'status' => 'active',
            'type' => 'consumer',
            'online' => 1,
        ]);

        return $customer->createToken("customer-{$customer->id}-token")->plainTextToken;
    }

    /**
     * Email a 3-minute reset code (always 123456 outside production) and reset the failed-attempt counter.
     */
    public function sendPasswordResetCode(string $email): void
    {
        $production = app()->isProduction();
        $otp = random_int(100000, 999999);
        $codeToStore = $production ? (string) $otp : '123456';

        $this->codes->putForEmail($email, Hash::make($codeToStore), now()->addMinutes(3));

        Cache::forget("otp_fail:{$email}");

        Mail::to($email)->send(new EmailVerifyRequestMail($production ? $otp : 123456));
    }

    /**
     * Check a reset code. Five wrong attempts discard the code.
     *
     * @throws GeneralException when there is no code, it's wrong, or it expired
     */
    public function verifyResetCode(string $email, mixed $otp): void
    {
        $verification = $this->codes->findForEmail($email);

        if (! $verification instanceof VerificationCode) {
            throw new GeneralException('No OTP request found for this email', 404);
        }

        if (! Hash::check((string) $otp, $verification->code)) {
            $failKey = "otp_fail:{$email}";
            $attempts = Cache::increment($failKey);
            if ($attempts >= self::MAX_OTP_ATTEMPTS) {
                $verification->delete();
                Cache::forget($failKey);
            }

            throw new GeneralException('Invalid Your OTP Number', 400);
        }

        if ($verification->expires_at->isPast()) {
            throw new GeneralException('Your OTP has been expired', 400);
        }
    }

    /**
     * Set a new password with a valid reset code, revoke every token, and discard the code.
     *
     * @throws GeneralException when the code is missing, wrong or expired, or the update fails
     */
    public function resetPassword(string $email, mixed $otp, string $password): void
    {
        $verification = $this->codes->findForEmail($email);

        if (! $verification instanceof VerificationCode) {
            throw new GeneralException('No OTP request found for this email', 404);
        }

        if (! Hash::check((string) $otp, $verification->code)) {
            throw new GeneralException('Invalid Your OTP Number', 400);
        }

        if ($verification->expires_at->isPast()) {
            throw new GeneralException('Your OTP has been expired', 400);
        }

        try {
            DB::beginTransaction();
            $contact = $this->contacts->findByEmail($email);
            $contact->password = Hash::make($password);
            $contact->save();

            $contact->tokens()->delete();

            $verification->delete();
            Cache::forget("otp_fail:{$email}");
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(app()->isProduction() ? 'Failed to reset password. Please try again later.' : $e->getMessage(), 500, $e);
        }
    }
}
