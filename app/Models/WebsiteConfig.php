<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's runtime-editable overrides.
 *
 * `layout_config` is the top-most layer in App\Support\SiteConfig's merge, so
 * anything valid in config/theme.php is valid here as JSON. `archetype` is
 * pulled out into its own column because it selects a whole bundle and is the
 * one field a website-settings screen should expose as a dropdown.
 *
 * The legacy theme/logo_path/primary_color/... columns are still here but no
 * longer read by the theme pipeline; they predate the token system.
 */
class WebsiteConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'theme', 'archetype', 'logo_path', 'favicon_path',
        'primary_color', 'secondary_color', 'font', 'layout_config',
    ];

    protected $casts = ['layout_config' => 'array'];

    /**
     * The override layer this row contributes to the resolved config.
     * Returning [] keeps SiteConfig's merge a no-op for empty rows.
     */
    public function toOverrideLayer(): array
    {
        $layer = array_filter((array) ($this->layout_config ?? []), static fn ($v) => $v !== null);

        if ($this->archetype) {
            $layer['theme']['archetype'] ??= $this->archetype;
        }

        return $layer;
    }
}
