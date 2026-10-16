<?php

namespace App\Rules;

use App\Support\IranianIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\ValidationException;

/**
 * Strict کد ملی: 10 digits with a valid check digit (Persian digits accepted).
 *
 * With `available: true` the rule also enforces the platform-wide uniqueness
 * decision (one کد ملی = one account across `users` + `students`), skipping
 * the given ids — the profile-edit use. Join/enrolment paths stay format-only
 * and run IranianIdentity::assertAvailable() themselves, because their
 * "removed rows are reusable" semantics differ per call site.
 */
final class NationalCode implements ValidationRule
{
    public function __construct(
        private readonly bool $available = false,
        private readonly ?int $ignoreUserId = null,
        private readonly ?int $ignoreStudentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! IranianIdentity::isValidNationalCode($value)) {
            $fail('کد ملی معتبر نیست.');

            return;
        }

        if (! $this->available) {
            return;
        }

        try {
            IranianIdentity::assertAvailable((string) $value, $this->ignoreUserId, $this->ignoreStudentId);
        } catch (ValidationException) {
            $fail('این کد ملی قبلاً در سیستم ثبت شده است.');
        }
    }
}
