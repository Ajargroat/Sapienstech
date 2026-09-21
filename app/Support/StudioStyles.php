<?php

namespace App\Support;

/**
 * Per-element style overrides for the fixed sections.
 *
 * Custom blocks already carry their own `--block-*` variables (see
 * BlockStyles), but the sections the platform itself renders had no way to
 * restyle a single instance: recolouring one button meant either changing the
 * shared button token, which repaints every button on the site, or editing the
 * Blade template.
 *
 * Overrides are keyed by the element's `data-studio-path`, which every fixed
 * section now emits, and are published as one stylesheet rather than inline
 * styles on each element. That keeps the public markup untouched (no extra
 * attributes, no per-element style threading through the partials) and it
 * degrades to nothing when a tenant has no overrides.
 *
 * This class is the security boundary for that stylesheet: only a path that
 * still resolves against the studio schema, and only values with an exactly
 * known shape, may reach it. Anything else is dropped silently, so a stored
 * layer written by an older or tampered build can never emit arbitrary CSS.
 */
class StudioStyles
{
    /**
     * A path is only addressable if it is one the schema actually publishes:
     * an exact field, a row of a list field, or a container some field lives
     * under (nav.cta is a container while nav.cta.label is the field).
     *
     * At least one segment must follow the section root, so the page-level
     * roots themselves (`public.landing`, `public.nav`, `public.footer`) are
     * not addressable: they name no element.
     */
    public const PATH_PATTERN = '/\Apublic\.(?:landing|nav|footer)(?:\.[a-z0-9_]+)+\z/';

    /**
     * Style key => [CSS property, unit, min, max]. Numeric keys are clamped to
     * their range; colours must match a 6-digit hex. A key mapped to `choice`
     * accepts one of a fixed word list and is emitted verbatim.
     */
    protected const KEYS = [
        'background' => ['background', '', 0, 0],
        'color' => ['color', '', 0, 0],
        'radius' => ['border-radius', 'px', 0, 128],
        'padding' => ['padding', 'px', 0, 128],
        'width' => ['inline-size', '%', 10, 100],
        'font_scale' => ['font-size', '%', 50, 400],
        'align' => ['text-align', 'choice', 0, 0],
    ];

    protected const ALIGN_CHOICES = ['start', 'center', 'end'];

    /**
     * Resolve a stored path against the studio schema. Exact fields, list rows
     * and container prefixes are addressable; everything else is not.
     */
    public static function validPath(string $path): bool
    {
        if (! preg_match(self::PATH_PATTERN, $path)) {
            return false;
        }

        if (StudioSchema::field($path) !== null) {
            return true;
        }

        // A row of a list field: strip the numeric index and require a list.
        if (preg_match('/\A(.+)\.([0-9]+)\z/', $path, $m)) {
            $owner = StudioSchema::field($m[1]);

            return ($owner['control'] ?? '') === 'list';
        }

        // A container (nav.cta) with real fields beneath it.
        foreach (StudioSchema::fields() as $known => $field) {
            if (str_starts_with($known, $path.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The declarations an override row contributes, already whitelisted.
     * An empty result means the row carries no usable style.
     *
     * @return array<string, string>
     */
    public static function declarations(array $row): array
    {
        $out = [];

        foreach (self::KEYS as $key => [$property, $unit, $min, $max]) {
            $value = $row[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if ($unit === 'choice') {
                if (is_string($value) && in_array($value, self::ALIGN_CHOICES, true)) {
                    $out[$property] = $value;
                }

                continue;
            }

            if ($unit === '') {
                if (is_string($value) && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value)) {
                    $out[$property] = $value;
                }

                continue;
            }

            if (is_numeric($value)
                && preg_match('/\A[0-9]+\z/', (string) $value)
                && $value >= $min && $value <= $max) {
                $out[$property] = (int) $value.$unit;
            }
        }

        return $out;
    }

    /**
     * Build the stylesheet for a stored override list. Later rows win over
     * earlier ones, which is what makes "last edit applies" intuitive when two
     * rows somehow address the same element.
     *
     * @param  array  $rows  list of ['path' => string, style keys...]
     */
    public static function css(array $rows): string
    {
        $rules = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $path = $row['path'] ?? null;

            if (! is_string($path) || ! self::validPath($path)) {
                continue;
            }

            $declarations = self::declarations($row);

            if ($declarations === []) {
                continue;
            }

            $body = '';

            foreach ($declarations as $property => $value) {
                $body .= $property.':'.$value.';';
            }

            // The path charset is already pinned to [a-z0-9_.] by
            // validPath(), so it cannot break out of the attribute selector.
            $rules[$path] = '[data-studio-path="'.$path.'"]{'.$body.'}';
        }

        return $rules === [] ? '' : implode('', array_values($rules));
    }
}
