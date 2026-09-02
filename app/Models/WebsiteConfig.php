<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WebsiteConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'theme', 'logo_path', 'favicon_path',
        'primary_color', 'secondary_color', 'font', 'layout_config',
    ];

    protected $casts = ['layout_config' => 'array'];
}
