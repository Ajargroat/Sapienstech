<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The single Theme Studio checkpoint (one per tenant+user): a named snapshot
 * of the editor FORM, stored as the field-name => values map the browser
 * submitted. It is inert until restored, and a restore re-submits it through
 * the ordinary save pipeline (scope=preview), so the stored bytes never reach
 * the config layer unvalidated. Draft is NOT publish.
 */
class StudioDraft extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
