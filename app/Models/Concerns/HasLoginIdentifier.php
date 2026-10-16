<?php

namespace App\Models\Concerns;

use App\Support\LoginIdentifier;
use Illuminate\Database\Eloquent\Builder;

/**
 * One login lookup for every guard: email, phone or کد ملی in one field.
 */
trait HasLoginIdentifier
{
    /**
     * Match one typed identifier against the model's 1-3 candidate columns.
     *
     * Tenancy stays the caller's concern — the caller's tenant scope (global
     * or explicit) applies to this query untouched.
     */
    public function scopeWhereIdentifier(Builder $query, string $raw): Builder
    {
        $candidates = LoginIdentifier::candidates($raw);

        if ($candidates === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $nested) use ($candidates) {
            foreach ($candidates as $column => $value) {
                $nested->orWhere($column, $value);
            }
        });
    }
}
