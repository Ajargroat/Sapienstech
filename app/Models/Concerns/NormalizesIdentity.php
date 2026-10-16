<?php

namespace App\Models\Concerns;

use App\Support\IranianIdentity;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Canonical stored form for phone / کد ملی on every write path.
 *
 * The setters normalise rather than reject: a value that cannot be
 * canonicalised is stored trimmed as typed (legacy rows stay readable),
 * while validation of *new* input belongs to the form requests and
 * StudentEnrollment.
 */
trait NormalizesIdentity
{
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null || $value === ''
                ? null
                : (IranianIdentity::normalizePhone((string) $value) ?? trim((string) $value)),
        );
    }

    protected function nationalCode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null || $value === ''
                ? null
                : (IranianIdentity::normalizeNationalCode((string) $value) ?? trim((string) $value)),
        );
    }

    /** The handle used for hand-over and display: email ?: phone ?: کد ملی. */
    public function loginIdentifier(): ?string
    {
        return $this->email ?: $this->phone ?: $this->national_code;
    }
}
