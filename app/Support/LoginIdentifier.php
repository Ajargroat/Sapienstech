<?php

namespace App\Support;

/**
 * Map one typed «شناسهٔ ورود» to the column candidates it may match.
 *
 * Deterministic by shape: an email-shaped value only tries `email`, a valid
 * phone only `phone`, a valid کد ملی only `national_code`. A value matching
 * no shape falls back to all three columns, so a partially typed or legacy
 * value never misses silently and the lookup never 500s. The shapes cannot
 * collide — a phone is 11 digits, a کد ملی exactly 10.
 */
final class LoginIdentifier
{
    /**
     * @return array<string, string> column => value (1-3 candidates)
     */
    public static function candidates(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        if (str_contains($raw, '@')) {
            return ['email' => mb_strtolower($raw)];
        }

        if ($phone = IranianIdentity::normalizePhone($raw)) {
            return ['phone' => $phone];
        }

        if (IranianIdentity::isValidNationalCode($raw)) {
            return ['national_code' => (string) IranianIdentity::normalizeNationalCode($raw)];
        }

        $fallback = IranianIdentity::toAsciiDigits($raw);

        return [
            'email' => mb_strtolower($raw),
            'phone' => $fallback,
            'national_code' => $fallback,
        ];
    }
}
