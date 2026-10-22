# Bespoke landing pages

A tenant may replace the shared, config-driven landing page with a hand-written
one. This folder is where those pages live. Every composed data feed a bespoke
page receives — identical to the shared landing — is governed by the contract in
[docs/landing-data.md](../../../docs/landing-data.md).

```
resources/landings/{slug}/
    landing.blade.php     the page — the tenant owns this markup completely
    login-shell.blade.php the login frame — presentation only (see below)
    meta.json             declares the surface published (see below)
    landing.css           optional: the page's own CSS
    landing.js            optional: the page's own behaviour
```

`{slug}` must be the tenant's `slug` from the `tenants` table and must match
`^[a-z0-9][a-z0-9-]*$`. Nothing else is accepted — see
`app/Support/LandingSurface.php`.

A tenant whose database slug differs from its shipped folder names the folder
with the deploy-time `tenant.surface` key in its config file. Production is the
live example: the tenant slug is `dr-kafi-mentoring`, the committed surfaces
are `resources/landings/drkafimentoring/`, and
`config/tenants/drkafimentoring.php` declares `'surface' => 'drkafimentoring'`.
The alias is read from config, never from a request, and is validated by the
same slug rule before it becomes a path.

## Why this exists

The shared landing page is one `@foreach` over a config list of section names,
dispatching each name to a variant from a fixed catalogue
(`resources/views/Public/landing.blade.php:71-79`). Every lever that system
offers is a dial on a shape the platform already owns, which is why two tenants
with different colours, fonts and animation settings still produce the same
page. A bespoke surface is the escape from that: real markup, written for one
tenant.

## How it is served

```
request → IdentifyTenant (host → domains)
        → LandingSurface::for(tenant())
             ├─ published + valid → view("{slug}.landing")
             └─ anything else     → view("public.landing")   ← untouched
```

`LandingSurface` **fails closed**. A missing file, an unreadable manifest, a
corrupt `meta.json`, a slug that could escape the directory — every one of those
returns `null`, and the shared landing page renders instead. A broken bespoke
page can never take a live tenant's domain down.

## meta.json

```json
{
  "enabled": true,
  "published_at": "2026-10-18T21:00:00+03:30",
  "published_by": "operator"
}
```

- `enabled` must be the JSON literal `true`. Not `"true"`, not `1` — a loose
  truthy value is treated as **not published**, because publishing a page is a
  decision that should be typed deliberately.
- No `meta.json` at all means **not published**.

## The contract a page must honour

A bespoke page is brand-consistent because of the shared token partials, not
because of the markup. Two includes are required, and
`tests/Feature/ThemeFontsTest.php` asserts both on every page in this folder:

```blade
@include('partials.theme-vars')    {{-- palette, type, shape, fonts, icons --}}
@include('partials.theme-attrs')   {{-- scheme, reduced motion, numerals --}}
```

Everything else is the tenant's: any structure, any layout, any animation.

### Write against the core token sheet, not the full one

`config/landing-tokens.php` names the 32 properties a bespoke page should use —
colour, type, shape, rhythm, state, motion. Every one already exists in the full
193-property token sheet, so the two can never disagree about what `--c-primary`
means; the smaller list just stops a page from being handed the platform's
layout geometry (`--sidebar-width`, `--hero-cols`, `--nav-height`), which is
precisely what makes different pages look the same.

Rules a generated page must follow:

1. **No hard-coded colours.** Use a token. `#0ea5e9` in a bespoke page is brand
   drift waiting for the tenant's next palette change.
2. **No inline `<script>`.** The page is same-origin code on a live domain. Put
   behaviour in `landing.js`; the CSP does not allow inline script.
3. **Honour `dir` and numerals.** The `<html>` tag gets `lang`/`dir` from the
   tenant; use logical properties (`margin-inline-start`, not `margin-left`).
4. **Respect reduced motion.** `data-animations="off"` is set by
   `theme-attrs`; every animation must be suppressible.
5. **Never invent tenant content.** Real copy, real images, real credentials.
   A page that invents a testimonial is worse than no page.

## Adding a page

`php artisan tenant:make` scaffolds both files from the checked-in stubs in
`resources/stubs/` and publishes the surface only after `LandingLint` passes.
For an existing tenant:

1. Create `resources/landings/{slug}/landing.blade.php`.
2. Include the two partials above.
3. Write the page against the core sheet.
4. Leave `meta.json` out (or `"enabled": false`) until it is reviewed.
5. Run the publish gate below; only then set `"enabled": true` to publish.

## The login shell

`login-shell.blade.php` is optional and follows exactly the same fail-closed
rules. It may change the frame, never the credential card: the shell includes
`auth.partials.login-card` unchanged, so both guards, `@csrf`, `throttle:login`,
the failed-login tab re-open and `whereIdentifier` stay platform wiring. A
missing/unpublished shell renders the shared `auth.login` page instead.

## Publish gate

A surface goes live only when the linter is green:

```sh
php artisan landing:lint drkafimentoring
php artisan landing:lint          # every surface
```

Non-zero exit means "do not publish" — fix the reported problems first.
`DrkafimentoringLandingTest::test_the_surface_passes_the_linter` pins the
shipped page green in the suite, so a regression fails tests before it can
be published. (There is no CI pipeline in this repo; the exit code plus the
test row is the gate.)

## Per-surface assets

A bespoke page's own static files live beside it in the web root, not in
the Vite graph:

```
public/landings/{slug}/
    landing.js    optional: the page's own behaviour
    landing.css   optional: the page's own CSS
```

Reference them with an explicit slug — never through `tenant_asset()`,
which resolves the `public/tenants/{slug}/` upload tree and knows nothing
about this folder:

```blade
<script src="{{ asset('landings/drkafimentoring/landing.js') }}"></script>
```

The CSP allows this without any policy change: `script_src` is `'self'`
(see `config/security.php`), which covers every same-origin file, and the
linter rejects the only thing it would not cover (inline `<script>`).
Nothing is added to `vite.config.js` `input` — these files are served
directly from `public/`, so a missing file degrades to a 404 for one
asset, never to a build or render failure.

Removing the folder, or setting `enabled` to `false`, reverts the tenant to the
shared landing page immediately. There is no migration and no rollback step
beyond the file itself — which is the point.
