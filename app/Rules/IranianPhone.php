<?php

namespace App\Rules;

use App\Support\IranianIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Strict Iranian mobile format, normalised (09123456789).
 *
 * Accepts the common shapes a person types — Persian digits, spaces, dashes,
 * +98 / 0098 prefixes — because the check runs through
 * IranianIdentity::normalizePhone(); storage is canonicalised by the model
 * setter either way.
 */
final class IranianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! IranianIdentity::isValidPhone($value)) {
            $fail('شمارهٔ موبایل معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹).');
        }
    }
}
