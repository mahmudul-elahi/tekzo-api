<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Mail\Customer\EmailVerifyRequestMail;
use App\Models\Contact\Contact;
use App\Models\Customer\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:contacts,email'],
        ]);

        $production = app()->isProduction();
        $otp = random_int(100000, 999999);
        $codeToStore = $production ? (string) $otp : '123456';

        VerificationCode::query()->updateOrCreate(
            ['email' => $request->email],
            [
                'code' => Hash::make($codeToStore),
                'expires_at' => now()->addMinutes(3),
            ]
        );

        Cache::forget("otp_fail:{$request->email}");

        Mail::to($request->email)->send(new EmailVerifyRequestMail($production ? $otp : 123456));

        return responseMessage('OTP sent to your email successfully');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required'],
        ]);

        $verification = VerificationCode::query()
            ->where('email', $request->email)
            ->first();

        if (! $verification) {
            return responseMessage('No OTP request found for this email', 404);
        }

        if (! Hash::check((string) $request->otp, $verification->code)) {
            $failKey = "otp_fail:{$request->email}";
            $attempts = Cache::increment($failKey);
            if ($attempts >= 5) {
                $verification->delete();
                Cache::forget($failKey);
            }

            return responseMessage('Invalid Your OTP Number', 400);
        }

        if ($verification->expires_at->isPast()) {
            return responseMessage('Your OTP has been expired', 400);
        }

        return responseMessage('OTP verified successfully');
    }

    public function confirmPassword(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $verification = VerificationCode::query()
            ->where('email', $request->email)
            ->first();

        if (! $verification) {
            return responseMessage('No OTP request found for this email', 404);
        }

        if (! Hash::check((string) $request->otp, $verification->code)) {
            return responseMessage('Invalid Your OTP Number', 400);
        }

        if ($verification->expires_at->isPast()) {
            return responseMessage('Your OTP has been expired', 400);
        }

        // update password
        try {
            DB::beginTransaction();
            $contact = Contact::query()->where('email', $request->email)->first();
            $contact->password = Hash::make($request->password);
            $contact->save();

            // Revoke all existing tokens for this customer
            $contact->tokens()->delete();

            // delete the verification code after successful password reset
            $verification->delete();
            Cache::forget("otp_fail:{$request->email}");
            DB::commit();

            return responseMessage('Password reset successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(app()->isProduction() ? 'Failed to reset password. Please try again later.' : $e->getMessage(), 500);
        }
    }
}
