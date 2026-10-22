<?php

namespace App\Support;

/**
 * The core token sheet a bespoke landing page is expected to use.
 *
 * This is a *view* over ThemeTokens, not a parallel system: every entry names
 * a path that ThemeTokens::VARS already emits, so a bespoke page and a
 * dashboard can never disagree about what `--c-primary` means. What it adds is
 * the boundary — a short list the generator writes against and the linter
 * enforces, which is what keeps generated pages on-brand without handing them
 * the 193-property sheet (whose layout tokens are exactly what makes two
 * different pages look the same).
 *
 * See config/landing-tokens.php for the curation rationale.
 */
class LandingTokens
{
    /** @return array<string, string> category => [theme path => css property] */
    public static function groups(): array
    {
        return (array) config('landing-tokens', []);
    }

    /** @return array<string, string> theme path => css custom property name */
    public static function map(): array
    {
        return array_merge(...array_values(self::groups() ?: [[]]));
    }

    /** @return list<string> css custom property names, e.g. ['c-primary', …] */
    public static function names(): array
    {
        return array_values(self::map());
    }

    /**
     * Core-sheet paths that ThemeTokens does not emit.
     *
     * Must always be empty: a core token that resolves to nothing would render
     * a bespoke page with an undefined variable and no error. The test suite
     * asserts this, and `landing:lint` reports it for humans.
     *
     * @return list<string>
     */
    public static function unknownPaths(): array
    {
        return array_values(array_diff(array_keys(self::map()), array_keys(ThemeTokens::VARS)));
    }

    /**
     * The sheet as it resolves for a tenant: css property => value.
     *
     * Only the core properties are returned, and only those with a value, so a
     * caller can emit exactly the contract and nothing else.
     *
     * @return array<string, string>
     */
    public static function resolved(?array $theme = null): array
    {
        $theme ??= site('theme');
        $vars = (array) ($theme['vars'] ?? []);
        $out = [];

        foreach (self::names() as $name) {
            if (isset($vars[$name]) && $vars[$name] !== '') {
                $out[$name] = (string) $vars[$name];
            }
        }

        return $out;
    }
}
