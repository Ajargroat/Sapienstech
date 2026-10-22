# Landing data contract

The landing page is a contract between two kinds of data: what a tenant may
configure, and what the platform feeds from its own database. This document is
the governed home of that contract. The machine-readable half is
`app/Support/LandingFeatures.php`; the ViewServiceProvider's landing composer
is its only consumer, and `tests/Unit/LandingFeaturesTest.php` fails loudly
when the manifest and the code drift apart.

## The three data classes

Every data feed on a visitor-facing landing belongs to exactly one data-life
class. The class decides who supplies the data and which checklist below is
mandatory.

| Class | What it is | Who supplies it | Example |
|---|---|---|---|
| **A** | config-only section | the SiteConfig chain (theme → archetype → tenant file → website_configs), in that merge order | hero copy, sections order, stats |
| **B** | DB-backed public read | a tenant model, fed to the page by the composer manifest | blog |
| **C** | DB-backed public submission | an unauthenticated visitor POSTing from the page | `/join` (join requests) |

## The flow

```
visitor → PageController.home → LandingSurface::for (fails closed)
    ├─ {slug}.landing ─┐
    └─ public.landing ─┴→ landing composer (view name patterns)
                            → LandingFeatures::composers() → per-feature provider
                                 blog: BlogPost::published() → toLandingItem()
                                       → same items on every fed surface
admin/consultant CRUD (consultant.feature:*) → model + migration
    (BelongsToTenant, globally-unique public key) → the same composer shape
visitor POST /join → named route + dedicated RateLimiter → pending request
    → consultant triage (never a student until approved)
```

A bespoke surface is a *layout* decision (`LandingSurface`), not a data
decision: composed class-B data is keyed to the manifest, so a bespoke page
receives exactly what the shared page would receive — including the empty-drop
semantics that remove a section instead of rendering an empty heading.

## Per-class checklist

### Class A — config-only

- Every key is proven against its consumer: a rendered page reads it somewhere,
  or the key does not exist.
- A studio lever for the section means a `config/studio.php` whitelist row —
  that file is the security boundary.
- Never invent tenant content: the system supplies the skin, the tenant
  supplies the words.

### Class B — DB-backed public read

- `BelongsToTenant` model plus a migration whose public key is globally unique
  (the join route binds it by value, without a tenant in the URL) — see
  `BlogPost::uniqueSlug()`.
- A `FormRequest` validation class on the write side; a rejection carries the
  vocabulary that would have satisfied it.
- Consultant-panel CRUD behind `consultant.feature:<name>` routes and a topnav
  entry.
- ONE composer shape for config and DB items (`toLandingItem()`), so variant
  templates render both without knowing the difference.
- A public detail route for a single item.
- Empty rows drop the section — never an empty heading on a live page.

### Class C — DB-backed public submission

- A feature flag plus a *named* POST route plus a *dedicated* `RateLimiter` —
  never a bare `throttle` default.
- A `FormRequest` validation class on the rewrite side.
- The tenant comes from the host, never from the form.
- A migration that is scoping-tested (row lands in the right tenant, only).
- A dedupe key, so one person re-submitting is a state change, not a spam row.
- Consultant triage plus a pending badge: the row is a *request*, not a student
  or a lead until approved.
- A throttle/abuse pin in the tests.

## Audit

| Surface | Class | Status |
|---|---|---|
| blog | B | conforming end-to-end — model, CRUD, composer shape, public detail route, empty-drop, test pins |
| `/join` (student join requests) | C | conforming end-to-end — flag, named route, `join-request` limiter, tenant-from-host, triage + badge |
| `Content` (about/contact pages) | B | **documented gap** — the model is a DB read (`PageController::about/contact`) with no writer anywhere in `app/`; if/when built, the CRUD must go through this contract |
| `/consultation` (consultation requests) | C | conforming end-to-end — flag default-off, named route + dedicated `consultation-request` limiter (first 429 at request #6), tenant-from-host, dedupe (same row, no re-email), queued email to tenant Users, triage + badge, shell + bespoke partial, studio rows enumerated |

Decided next steps (carried from the accepted plan): the contact/consultation
form shipped as the **first feature** pushed through this contract (flag
`features.consultation_requests`, default off; a tenant opts in via
`sections` + the Studio toggle). `Content` remains a documented gap until its
CRUD is built through this contract.


## The brand seam (S2/S3 additions, 2026-10)

### Brand-group lock (`theme.brand_lock`)

The tenant file can now pin token groups against the personal ("just for me")
layer. The key is `brand_lock`, a top-level key of the `theme` block whose
default `null` locks nothing — verifiably a no-op for every tenant that does
not set it (`ApplyPersonalTheme` returns the layer untouched).

| Piece | Where | Contract |
|---|---|---|
| Baseline key | `config/theme.php` (`theme.brand_lock`) | `null` = current full-override behaviour; a list of group names opts in |
| Vocabulary | `app/Support/ThemeTokens.php` `VARS` top-level groups (`colors`, `typography`, `gradients`, …) | Only real group names take effect; a misspelled entry is inert, never a crash and never a lock of a neighbour group |
| Filter | `app/Http/Middleware/ApplyPersonalTheme` `effectiveLayer()` | Locked groups' `theme.<group>` subtrees are dropped from BOTH merge inputs (saved layer and live preview) BEFORE the structural merge, so a locked path never reaches `SiteConfig::personalize()` |
| Read point | `ApplyPersonalTheme::brandLock()` | Read from the layers BELOW the personal layer (files, archetype, tenant file, DB) — with `personalize()` not yet called in the request — so the layer being policed can never lift it |
| Owner | the tenant itself, deploy-time | NOT a Studio key: no whitelist entry, no UI; the personal layer is exactly what this key fences off |

The invariant the tests pin (`tests/Feature/BrandConsistencyTest.php`, cases
(d) and the vocabulary test): with a personal layer AND a lock, the brand
variables the owner's surfaces emit re-equalize with the guest pages; a
personal tweak outside the locked groups still answers its owner; a tenant
without the lock behaves byte-identically to before.

### Bespoke login shell (`AuthSurface` + the `*.login-shell` composer entry)

A tenant may publish a hand-written login page at
`resources/landings/{slug}/login-shell.blade.php` next to its custom landing.
`app/Support/AuthSurface.php` is the only dispatch decision: the slug rule,
tenant existence, `meta.json` `enabled: true`, and the shell file itself must
all hold, and ANY failure — including a corrupt manifest or an unknown slug —
renders the shared `auth.login` card instead. The shell replaces the layout
decision only: it joins the same public composer (`app/Providers/ViewServiceProvider.php`,
`*.login-shell`) as `*.landing`, so it renders the tenant's own tokens,
labels and content.

The LOCK contract travels with the shell: both guards' forms, CSRF,
`throttle:login` and all session handling are platform wiring the shell
cannot touch (`LoginController::showLoginForm()` only picks the view).
`tests/Feature/AuthSurfaceTest.php` pins the fail-closed dispatch, the
second-tenant isolation, the route/throttle/controller introspection, and —
together with the shell entries in `ThemeFontsTest`/`ThemeIconsTest` (both
discover shells by glob, so the invariant never depends on a hand-kept list)
— the one-token-sheet/one-font-pipeline rule on hand-written shells.
