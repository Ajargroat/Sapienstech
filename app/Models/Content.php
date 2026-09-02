<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'title', 'body'];
}
