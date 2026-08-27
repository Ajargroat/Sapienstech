<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    // Relationships to websiteConfig(), features(), contents(), users(),
    // and students() belong here too -- add them as those models get built.
}
