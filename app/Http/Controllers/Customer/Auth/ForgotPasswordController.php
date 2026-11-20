<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Exceptions\GeneralException;
use App\Http\Controllers\Controller;
use App\Models\Contact\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Customer\VerificationCode;
use App\Mail\Customer\EmailVerifyRequestMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ForgotPasswordController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:contacts,email'],
        ]);


        $otp = random_int(100000, 999999);

        VerificationCode::query()->updateOrCreate(
            ['email' => $request->email],
            [
                'code' => $otp,
                'expires_at' => now()->addMinutes(3),
            ]
        );


        Mail::to($request->email)->send(new EmailVerifyRequestMail($otp));


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

        if (!$verification) {
            return responseMessage('No OTP request found for this email', 404);
        }

        if ($request->otp != $verification->code) {
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
            'password' => 'required|min:6|confirmed'
        ]);

        $verification = VerificationCode::query()
            ->where('email', $request->email)
            ->first();

        if (!$verification) {
            return responseMessage('No OTP request found for this email', 404);
        }

        if ($request->otp !== $verification->code) {
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

            // delete the verification code after successful password reset
            $verification->delete();
            DB::commit();

            return responseMessage('Password reset successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            throw new GeneralException(app()->isProduction() ? 'Failed to reset password. Please try again later.' : $e->getMessage(), 500);
        }
    }
}
