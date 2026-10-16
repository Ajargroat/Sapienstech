<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The one home for Iranian identity formats (کد ملی, موبایل).
 *
 * Every write path, validator and form request goes through here, so the
 * stored form of a phone or a national code is identical no matter which
 * shape (Persian digits, +98, spaces) the person typed.
 */
final class IranianIdentity
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Convert Persian/Arabic-Indic digits to ASCII, leaving other characters. */
    public static function toAsciiDigits(string $value): string
    {
        $map = [];

        foreach (self::PERSIAN_DIGITS as $i => $digit) {
            $map[$digit] = (string) $i;
        }

        foreach (self::ARABIC_DIGITS as $i => $digit) {
            $map[$digit] = (string) $i;
        }

        return strtr($value, $map);
    }

    /** Digits only, or null when nothing usable remains. */
    public static function normalizeNationalCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', self::toAsciiDigits($value));

        return $digits === '' ? null : $digits;
    }

    /** 10 digits with a valid check digit; all-same-digit codes are not issued. */
    public static function isValidNationalCode(?string $value): bool
    {
        $code = self::normalizeNationalCode($value);

        if ($code === null || preg_match('/^\d{10}$/', $code) !== 1) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $code) === 1) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $code[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }

    /** Canonical mobile form (09xxxxxxxxx), or null when the input is not an Iranian mobile. */
    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', self::toAsciiDigits($value));

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0098') && strlen($digits) === 14) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return preg_match('/^09\d{9}$/', $digits) === 1 ? $digits : null;
    }

    public static function isValidPhone(?string $value): bool
    {
        return self::normalizePhone($value) !== null;
    }

    /** A valid checksum sample for factories and tests. */
    public static function randomValidNationalCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 9; $i++) {
                $code .= (string) random_int(0, 9);
            }

            $sum = 0;

            for ($i = 0; $i < 9; $i++) {
                $sum += ((int) $code[$i]) * (10 - $i);
            }

            $remainder = $sum % 11;
            $code .= (string) ($remainder < 2 ? $remainder : 11 - $remainder);
        } while (! self::isValidNationalCode($code));

        return $code;
    }

    /**
     * Best-effort global availability check for a کد ملی.
     *
     * `national_code` is unique per table, but one DB index cannot span
     * `users` and `students`; uniqueness across both is this check plus the
     * two indexes (documented as best-effort under concurrency in
     * docs/identity.md). `$includeRemoved = false` lets the re-application
     * path treat a removed roster row as reusable instead of blocking.
     */
    public static function assertAvailable(
        ?string $nationalCode,
        ?int $ignoreUserId = null,
        ?int $ignoreStudentId = null,
        bool $includeRemoved = true,
    ): void {
        $code = self::normalizeNationalCode($nationalCode);

        if ($code === null) {
            return;
        }

        $user = User::withoutGlobalScopes()
            ->where('national_code', $code)
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->exists();

        $student = Student::withoutGlobalScopes()
            ->where('national_code', $code)
            ->when($ignoreStudentId, fn ($query) => $query->where('id', '!=', $ignoreStudentId))
            ->when(! $includeRemoved, fn ($query) => $query->whereNull('removed_at'))
            ->exists();

        if ($user || $student) {
            throw ValidationException::withMessages([
                'national_code' => 'این کد ملی قبلاً در سیستم ثبت شده است.',
            ]);
        }
    }
}
