<?php

namespace App\Support;

/**
 * The academic vocabulary of the platform, resolved through site() like
 * every other tenant-facing knob.
 *
 * This system's primary audience is middle school (متوسطه اول, grades 7-9
 * — «هفتم/هشتم/نهم»). High school (10-12) stays available; the default
 * group decides which vocabulary the pickers lead with, and a tenant may
 * override every list through the usual config layers (tenant file, DB
 * layer, archetype) without touching code.
 *
 * Values match what the students table stores: plain Persian grade names
 * (the existing konkour rows already hold «دهم» … as free strings).
 */
class Academics
{
    /** @return array<string, array{label:string, grades:list<string>}> */
    public static function groups(): array
    {
        $groups = site('academics.grade_groups', []);

        if ($groups === []) {
            $groups = (array) config('theme.academics.grade_groups', []);
        }

        return is_array($groups) ? $groups : [];
    }

    /** All grades, intermediate first — the "available for everyone" list. */
    public static function grades(): array
    {
        $grades = [];

        foreach (self::groups() as $group) {
            foreach ((array) ($group['grades'] ?? []) as $grade) {
                $grades[$grade] = $group['label'] ?? null;
            }
        }

        return $grades;
    }

    /** select options: grade => «پایه هفتم (متوسطه اول)» style labels. */
    public static function gradeOptions(): array
    {
        $options = [];

        foreach (self::grades() as $grade => $groupLabel) {
            $options[$grade] = $groupLabel ? "{$grade} ({$groupLabel})" : $grade;
        }

        return $options;
    }

    /** Subject suggestions for the pickers (datalist, not a hard whitelist). */
    public static function subjects(): array
    {
        return array_values((array) site('academics.subjects', []));
    }

    /** Which group the platform leads with (middle school by default). */
    public static function defaultGroup(): string
    {
        return (string) (site('academics.default_group') ?? 'intermediate');
    }
}
