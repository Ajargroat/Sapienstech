<?php

namespace App\Support;

/**
 * The tenant's paying plans («طرح‌ها») — the priced packages on the landing
 * page, and the only place a join request's amount may come from.
 *
 * The public join form lets a visitor name the plan they paid for, never the
 * amount: the price is read from this config through the slug the visitor
 * submitted, so a crafted POST cannot name its own price. An unknown slug
 * resolves to null and the caller falls back to `public.landing.join.amount`.
 *
 * A tenant with no plans configured gets an empty option set, which is what
 * keeps this a verified no-op for every tenant that has not opted in.
 */
final class Plans
{
    /**
     * Visible plans, keyed by slug, in configured order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function options(): array
    {
        $out = [];

        foreach ((array) site('public.landing.plans.items', []) as $row) {
            if (! is_array($row) || ! ($row['visible'] ?? true)) {
                continue;
            }

            $slug = trim((string) ($row['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $out[$slug] = $row;
        }

        return $out;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::options());
    }

    /**
     * The configured price for a slug, in tomans.
     *
     * Null means "no such plan" — distinct from a real price of zero, which
     * means the tenant prices that plan on request.
     */
    public static function priceFor(?string $slug): ?int
    {
        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        $options = self::options();

        return array_key_exists($slug, $options)
            ? max(0, (int) ($options[$slug]['price'] ?? 0))
            : null;
    }

    /**
     * A plan's features, one entry per line.
     *
     * Newline-separated rather than a nested list because the studio's `list`
     * control cannot nest a list inside a row: a nested shape would leave the
     * field file-owned and uneditable in the Appearance Studio.
     *
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    public static function features(array $plan): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) ($plan['features'] ?? '')) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => $line !== ''
        ));
    }
}
