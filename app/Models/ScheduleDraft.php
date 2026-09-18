<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScheduleDraft extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'week_start_date',
        'blocks',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'blocks' => 'array',
        ];
    }
}
