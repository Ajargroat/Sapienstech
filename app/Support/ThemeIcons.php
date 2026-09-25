<?php

namespace App\Support;

final class ThemeIcons
{
    /** The catalog is shared with the asset generator so choices cannot drift. */
    public static function sets(): array
    {
        return json_decode(file_get_contents(resource_path('icons/catalog.json')), true, 512, JSON_THROW_ON_ERROR)['sets'];
    }

    /**
     * Studio choices: every set plus the Font Awesome fallback itself. The
     * catalog lists only the replaceable sets, so the default choice is added
     * here rather than duplicated there.
     */
    public static function choices(): array
    {
        return ['font-awesome' => 'Default (Font Awesome)'] + self::sets();
    }

    public static function selected(mixed $set): string
    {
        return is_string($set) && isset(self::sets()[$set]) ? $set : 'font-awesome';
    }

    /**
     * Preview glyphs for the studio's icon-set picker: for each requested
     * concept, the data URI of the concrete icon the chosen set renders.
     * Null for Font Awesome (the glyph comes from its stylesheet) and for a
     * concept a set lacks — the caller falls back to the FA glyph there.
     *
     * @param  string  $set  set id (an unknown id previews as font-awesome)
     * @param  list<string>  $concepts  semantic concepts in display order
     * @return list<array{name: string, set: string, dataUri: ?string}>
     */
    public static function preview(string $set, array $concepts): array
    {
        $manifest = resource_path('icons/preview-manifest.json');
        $data = is_file($manifest)
            ? json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR)
            : [];
        $sets = $data['sets'] ?? [];

        $chosen = self::selected($set);
        $out = [];

        foreach ($concepts as $concept) {
            $uri = $sets[$chosen][$concept] ?? null;
            $out[] = [
                'name' => $concept,
                'set' => $chosen,
                'dataUri' => is_string($uri) ? $uri : null,
            ];
        }

        return $out;
    }
}
