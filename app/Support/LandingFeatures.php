<?php

namespace App\Support;

use App\Models\BlogPost;

/**
 * The landing feature contract, as code.
 *
 * Every data feed a visitor-facing landing page consumes belongs to one of
 * three data-life classes, and each class carries a mandatory backend
 * checklist (docs/landing-data.md holds the prose; this file holds the
 * machine-readable half):
 *
 *   A — config-only: the section is pure `site()` configuration. Its data is
 *       the SiteConfig layer chain (theme → archetype → tenant →
 *       website_configs) — nothing here touches the database for it.
 *   B — DB-backed public read: a tenant model feeds the landing through THIS
 *       manifest, so the shared page and every bespoke `{slug}.landing`
 *       surface receive identical composed items. Entries of this class are
 *       consumed by the ViewServiceProvider landing composer.
 *   C — DB-backed public submission: an unauthenticated visitor POSTs from
 *       the landing. The class needs no composer injection, so its entry
 *       exists to keep the contract's keys (flag ✓, named route ✓, dedicated
 *       RateLimiter ✓, FormRequest ✓, tenant-from-host ✓, triage ✓) in one
 *       auditable place — tests/Unit/LandingFeaturesTest.php verifies every
 *       referenced thing exists.
 *
 * A manifest entry nobody consumes is a defect, not documentation: the unit
 * test proves each class-B entry's views are served by the composer, and the
 * composer source is pinned to the `composers()` seam so silent re-keying to
 * one hard-coded view name fails the test.
 *
 * Adding a feature later means ALL of: append one entry here, add one row to
 * docs/landing-data.md's audit table, and update the count pin in
 * tests/Unit/LandingFeaturesTest.php. The count never grows silently.
 */
class LandingFeatures
{
    /**
     * The registered features, keyed by id, in registration order.
     *
     * Today's count — `blog` (class B), `join` and `consultation` (both
     * class C) — is pinned by
     * test_manifest_registers_exactly_today_three_features; growth is a
     * reviewed, documented event, never an accident.
     */
    public static function manifest(): array
    {
        return [
            'blog' => [
                'class' => 'B',
                'model' => BlogPost::class,
                'views' => ['public.landing', '*.landing'],
                'section' => 'blog',
                'see_all_route' => 'blog.index',
                'drop_section_when_empty' => true,
                // Reference provider, lifted verbatim from the composer that
                // shipped it: latest published posts shaped exactly like the
                // config-driven landing items. Receives the section's config
                // array (its `count` is the section's own lever) and returns
                // the item list.
                'provider' => static fn (array $section): array => BlogPost::published()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->take(max(1, (int) ($section['count'] ?? 3)))
                    ->get()
                    ->map->toLandingItem()
                    ->all(),
            ],

            // Class C feeds no composer: the join section renders through its
            // own partial posting to a named, throttled route. Declared here
            // so the submission contract's keys have one auditable home.
            'join' => [
                'class' => 'C',
                'views' => [],
                'route' => 'join.store',
                'throttle' => 'join-request',
                'flag' => 'features.student_join_requests',
            ],

            // Class C like `join`: the consultation form posts to its named,
            // throttled route; the partial rides `$L`/`cfg` (no composer
            // injection), so the views list stays empty and bespoke == shell
            // holds by construction - both surfaces include the same partial.
            'consultation' => [
                'class' => 'C',
                'views' => [],
                'route' => 'consultation.store',
                'throttle' => 'consultation-request',
                'flag' => 'features.consultation_requests',
            ],
        ];
    }

    /**
     * The class-B entries in manifest order — the composer's injection loop.
     *
     * One named seam (instead of the composer filtering the manifest
     * inline) so both the composer and the drift test have a single thing
     * to attach to.
     *
     * @return array<string, array>
     */
    public static function composers(): array
    {
        return array_filter(self::manifest(), fn (array $feature): bool => $feature['class'] === 'B');
    }
}
