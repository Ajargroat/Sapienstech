<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
