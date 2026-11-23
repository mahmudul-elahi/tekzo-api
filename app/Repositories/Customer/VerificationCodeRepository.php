<?php

namespace App\Repositories\Customer;

use App\Models\Customer\VerificationCode;
use App\Repositories\BaseRepository;

class VerificationCodeRepository extends BaseRepository
{
    public function __construct(VerificationCode $verificationCode)
    {
        $this->model = $verificationCode;
    }

    /**
     * Replace the email's code (one row per email).
     */
    public function putForEmail(string $email, mixed $code, mixed $expiresAt): void
    {
        $this->query()->updateOrCreate(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Replace the phone number's code (one row per phone number).
     */
    public function putForPhone(string $phoneNumber, mixed $code, mixed $expiresAt): void
    {
        $this->query()->updateOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'code' => $code,
                'expires_at' => $expiresAt,
            ]
        );
    }

    public function findForEmail(string $email): ?VerificationCode
    {
        return $this->query()
            ->where('email', $email)
            ->first();
    }

    public function findUnexpiredForEmail(string $email): ?VerificationCode
    {
        return $this->query()->where('email', $email)
            ->where('expires_at', '>=', now())
            ->first();
    }

    /**
     * Phone codes are stored in plain text, so the code itself is part of the lookup.
     */
    public function findUnexpiredForPhone(string $phoneNumber, mixed $code): ?VerificationCode
    {
        return $this->query()
            ->where('phone_number', $phoneNumber)
            ->where('code', $code)
            ->where('expires_at', '>=', now())
            ->first();
    }
}
