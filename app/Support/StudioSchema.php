<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Reads config/studio.php and exposes it as the studio's whitelist + form
 * model. This class is the security boundary: a path that is not declared
 * here cannot be read into the form or written by it, and every value is
 * validated against the rules declared alongside the path.
 *
 * The studio writes *sparse diffs* — only keys the tenant actually changed
 * relative to the resolved baseline. That keeps layout_config small and,
 * more importantly, means "reset this token" is just forgetting the key so
 * the lower layers (archetype / tenant file / baseline) show through again.
 */
class StudioSchema
{
    /** @return array<string, array{label:string, fields: array}> */
    public static function groups(): array
    {
        return (array) config('studio.groups', []);
    }

    /**
     * Flat path => field definition, across every group.
     *
     * @return array<string, array>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['fields'] as $field) {
                $field['group'] = $groupKey;
                $field['group_label'] = $group['label'];
                $field['group_admin'] = (bool) ($group['admin'] ?? false);
                $fields[$field['path']] = $field;
            }
        }

        return $fields;
    }

    public static function field(string $path): ?array
    {
        return self::fields()[$path] ?? null;
    }

    /**
     * Validation rules keyed by dotted path, for every non-image field.
     * Image fields are validated separately as uploaded files.
     *
     * @return array<string, array>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::fields() as $path => $field) {
            if (($field['control'] ?? '') === 'image') {
                continue;
            }

            // The archetype list lives on disk, not in config, so its `in:`
            // rule is generated from the actual bundles.
            if (($field['control'] ?? '') === 'archetype') {
                $rules[$path] = ['required', 'in:'.implode(',', self::archetypes())];
                continue;
            }

            $rules[$path] = $field['rules'] ?? [];
        }

        return $rules;
    }

    /** @return list<string> archetype bundle names (config/archetypes/*.php) */
    public static function archetypes(): array
    {
        $files = glob(config_path('archetypes').DIRECTORY_SEPARATOR.'*.php') ?: [];

        return array_values(array_map(static fn ($f) => basename($f, '.php'), $files));
    }

    /** @return array<string, array> image-file rules keyed by dotted path */
    public static function imageRules(): array
    {
        $rules = [];

        foreach (self::fields() as $path => $field) {
            if (($field['control'] ?? '') === 'image') {
                $rules[$path] = $field['rules'] ?? [];
            }
        }

        return $rules;
    }

    /**
     * The current value of a path from the resolved site config.
     *
     * @param  array  $resolved  the full resolved tree (site()->all())
     */
    public static function current(array $resolved, string $path): mixed
    {
        return Arr::get($resolved, $path);
    }

    /**
     * Flatten a stored override layer into the set of dotted paths it touches.
     *
     * @return array<string, mixed> path => stored value
     */
    public static function overriddenPaths(?array $layer): array
    {
        if (! $layer) {
            return [];
        }

        $flat = [];

        $walk = function ($node, $prefix) use (&$walk, &$flat) {
            foreach ((array) $node as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

                if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                    $walk($value, $path);
                } else {
                    $flat[$path] = $value;
                }
            }
        };

        $walk($layer, '');

        return $flat;
    }

    /**
     * Turn a dotted path into an HTML field name in bracket notation, so the
     * browser submits a nested array Laravel can validate by the same dot path.
     */
    public static function htmlName(string $path, bool $multi = false): string
    {
        $segments = explode('.', $path);
        $name = array_shift($segments);

        foreach ($segments as $segment) {
            $name .= '['.$segment.']';
        }

        return $name.($multi ? '[]' : '');
    }

    /**
     * Restrict a submitted value tree to whitelisted paths and coerce each to
     * the shape its control expects. Defense in depth on top of validation:
     * even a passing value is normalized here before it reaches the writer.
     *
     * @param  array<string, mixed>  $validated  dotted path => value
     * @return array<string, mixed> dotted path => normalized value (or null to forget)
     */
    public static function normalize(array $validated): array
    {
        $out = [];

        foreach ($validated as $path => $value) {
            $field = self::field((string) $path);

            if (! $field) {
                continue; // not whitelisted — drop silently
            }

            $out[(string) $path] = match ($field['control']) {
                'toggle' => (bool) $value,
                'number' => $value === '' || $value === null ? null : (int) $value,
                // The range bar composes "<number><unit>" into the named text
                // input; values keep the shape their rules declare — integer-
                // backed fields (stagger_ms) store ints, everything else stays
                // the trimmed string, so ".94" never flips to 0.94 and trips
                // its own `string` rule on the next round-trip. An emptied
                // optional field still forgets its override via null.
                'range' => match (true) {
                    ! is_string($value) => $value,
                    trim($value) === '' => null,
                    in_array('integer', (array) ($field['rules'] ?? []), true) => (int) $value,
                    default => trim($value),
                },
                // Sections arrive as a checkbox array; keep only whitelisted
                // names, preserving the submitted (DOM) order, then re-pin the
                // locked ones so a forged or stale submission cannot move or
                // hide them.
                'sections' => self::pinSections(array_intersect(
                    array_map('strval', is_array($value) ? $value : []),
                    array_keys((array) ($field['options'] ?? [])),
                ), $field),
                // An emptied optional field means "stop overriding": '' becomes
                // null so the writer forgets the key and the lower layers
                // (tenant file / archetype / baseline) show through again.
                // Required fields never reach here as '' — validation stops them.
                default => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value,
            };
        }

        return $out;
    }

    /**
     * Enforce a sections field's `locked` list: locked names are always
     * present and pinned to their canonical slot (their index in `options`),
     * while every other name keeps the given order around them. Duplicates
     * are dropped. Used both when rendering the form and when normalizing a
     * submission, so the invariant holds no matter what the browser sends.
     *
     * @param  array<string>  $names  section names in the desired order
     * @return list<string>
     */
    public static function pinSections(array $names, array $field): array
    {
        $options = array_keys((array) ($field['options'] ?? []));

        $locked = array_values(array_intersect($options, (array) ($field['locked'] ?? [])));

        $free = array_values(array_unique(array_filter(
            array_values($names),
            fn ($name) => ! in_array($name, $locked, true),
        )));

        foreach ($locked as $name) {
            // Canonical slot = position in the options list, clamped to the
            // end when the tenant hid enough sections to shrink the page.
            $at = min((int) array_search($name, $options, true), count($free));
            array_splice($free, $at, 0, [$name]);
        }

        return $free;
    }

    /**
     * How the live preview must react to a change on this path:
     *
     *  - 'token':  the path publishes as a CSS custom property (or as part of
     *              a colour-scheme block), so re-painting the variables is
     *              enough — no document reload;
     *  - 'reload': the value reaches the markup itself (variants, copy,
     *              structure), so the preview has to be re-rendered.
     *
     * The classification is derived from ThemeTokens::VARS rather than a
     * hand-kept list, so it can never drift from what actually becomes CSS.
     */
    public static function liveMode(string $path): string
    {
        if (str_starts_with($path, 'theme.schemes.')) {
            return 'token';
        }

        if (str_starts_with($path, 'theme.')) {
            return isset(ThemeTokens::VARS[substr($path, strlen('theme.'))]) ? 'token' : 'reload';
        }

        return 'reload';
    }

    /**
     * Diff normalized values against the resolved baseline, returning only the
     * keys that actually differ (the sparse override to persist).
     *
     * @param  array<string, mixed>  $values     dotted path => new value
     * @param  array  $resolved                  current resolved tree
     * @return array<string, mixed> dotted path => value (changed only)
     */
    public static function diff(array $values, array $resolved): array
    {
        $changes = [];

        foreach ($values as $path => $value) {
            $current = Arr::get($resolved, $path);

            if (self::looseEqual($current, $value)) {
                continue;
            }

            $changes[$path] = $value;
        }

        return $changes;
    }

    protected static function looseEqual(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        if (is_array($a) || is_array($b)) {
            return $a == $b;
        }

        // Compare scalars as strings so "3" and 3 (number inputs) match.
        return (string) $a === (string) $b;
    }
}
