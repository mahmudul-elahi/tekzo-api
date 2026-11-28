<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bangladeshi mobile numbers, stored as +8801XXXXXXXXX.
 *
 * Older records may hold the same number as 01XXXXXXXXX or 8801XXXXXXXXX, so lookups should
 * match every stored form returned by variants().
 */
final class PhoneNumber
{
    /** A Bangladeshi mobile number in the canonical stored form. */
    public const string PATTERN = '/^\+8801[3-9]\d{8}$/';

    /**
     * Bring a mobile number to +8801XXXXXXXXX. Input that is not a recognisable Bangladeshi
     * mobile number comes back with only its digits, so validation can reject it.
     */
    public static function normalize(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        return match (true) {
            str_starts_with($digits, '01') => '+88'.$digits,
            str_starts_with($digits, '8801') => '+'.$digits,
            str_starts_with($digits, '801') => '+8'.$digits,
            default => $digits,
        };
    }

    public static function isValid(string $phone): bool
    {
        return preg_match(self::PATTERN, $phone) === 1;
    }

    /**
     * Every form the number may be stored in, starting with the canonical one.
     *
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $normalized = self::normalize($phone);

        if (! self::isValid($normalized)) {
            return [$normalized];
        }

        $local = substr($normalized, 3);

        return [$normalized, '88'.$local, $local];
    }
}
