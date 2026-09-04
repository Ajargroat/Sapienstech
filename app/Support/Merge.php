<?php

namespace App\Support;

/**
 * Config merging for the tenant resolution pipeline.
 *
 * Deliberately NOT array_replace_recursive(): that merges list-shaped arrays
 * index-by-index, so a tenant overriding an 8-item `sections` list with a
 * 5-item one would silently keep items 5-7 from the baseline. Lists replace
 * wholesale; only associative arrays recurse.
 */
final class Merge
{
    public static function structural(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (! is_array($value) || array_is_list($value)) {
                $base[$key] = $value;
                continue;
            }

            $current = $base[$key] ?? null;

            $base[$key] = is_array($current) && ! array_is_list($current)
                ? self::structural($current, $value)
                : $value;
        }

        return $base;
    }
}
