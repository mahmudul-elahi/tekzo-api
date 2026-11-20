<x-mail::message>
# Email Verification

Hello,

Your email verification OTP code is:

<x-mail::panel>
    # {{ $otp }}
</x-mail::panel>

This OTP will expire in **3 minutes**.

If you did not request this verification, please ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
