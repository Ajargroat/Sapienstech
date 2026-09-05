# Sapienstech — Multi-Tenant Consultant Platform

PHP 8.2+, Composer, Node.js 20+.

## Quick start

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

With `TENANT_FALLBACK_DOMAINS="localhost,127.0.0.1"` (the local default) you can
open `http://localhost:8000/` immediately — it serves whichever tenant owns a
primary domain. **Leave that variable empty in production.**

## How tenancy works

Two things resolve a tenant, and they are independent:

| Concern | Mechanism |
|---|---|
| **Who is this visitor looking at?** | `IdentifyTenant` middleware matches `Request::getHost()` against the `domains` table. Unknown host → 404. |
| **What does it look like?** | `App\Support\SiteConfig` merges layered config, then `ThemeTokens` derives the rest. |

Data isolation is separate again: `BelongsToTenant` scopes every query, and
`User::canLoginThrough()` pins consultants to a domain. See
`tests/Feature/TenantIsolationTest.php` and `DomainAccessTest.php`.

Sessions are deliberately **host-only**. Do not set `SESSION_DOMAIN` to a shared
parent "so login works across tenants" — that breaks isolation.

## Adding a tenant

```powershell
php artisan tenant:make "مؤسسه معین" moein `
    --domain=moeinacademy.test `
    --archetype=editorial_serif `
    --admin-email=admin@moein.test
```

That creates the `tenants` / `domains` / `website_configs` rows, writes
`config/tenants/moein.php`, makes `public/tenants/moein/images/`, seeds a
`tenant_admin`, and busts the domain cache.

Then, **outside the repo** (you do these by hand):

1. Add `127.0.0.1  moeinacademy.test` to `C:\Windows\System32\drivers\etc\hosts`
   *as Administrator*, then `ipconfig /flushdns`.
2. Add the host to `VITE_ALLOWED_HOSTS` in `.env` if you use `npm run dev`.
   Vite ≥ 6 rejects unknown `Host` headers, which otherwise looks like a 403 on
   every asset.
3. Validate: `php artisan tenant:validate moein`

Prefer `.test` over `.local` for local domains — `.local` is reserved for mDNS
(RFC 6761) and will not resolve on macOS or Linux.

## The config layers

Later wins. All merging goes through `App\Support\Merge::structural()`, which
**replaces lists wholesale** — `array_replace_recursive()` would splice a
5-section override into an 8-section baseline and silently keep the leftovers.

```
config/theme.php                 platform baseline; every tenant shares it
  └─ config/archetypes/{name}.php  a visual identity bundle (see below)
      └─ config/tenants/{slug}.php  this tenant's identity + copy
          └─ website_configs.layout_config  runtime edits, no deploy
              └─ site_override([...])  current request only (tests, previews)
```

Read any of it with `site('colors.primary')`, `site('public.landing.hero')`.
`site()` accepts both `theme.colors.x` and the shorthand `colors.x`.

A tenant file should be **short** — only keys that differ:

```php
return [
    'tenant' => ['name' => 'آکادمی جنت'],
    'theme'  => ['archetype' => 'aurora_glass'],
];
```

## Archetypes: why presets were replaced

`config/consultant.php` used to carry a `theme.presets` array and a
`theme.preset` name. **Nothing ever merged them into `colors`** — the value was
only echoed as a `data-theme-preset` attribute that no CSS rule matched. Picking
a preset changed nothing on screen.

An archetype is a real bundle, and it changes **structure as well as styling**.
That distinction is the whole point: a preset that only swaps colours cannot stop
two tenants looking like the same product.

| | `aurora_glass` | `editorial_serif` | `brutalist_mono` |
|---|---|---|---|
| surface | glass (blur 20px) | paper + grain | flat |
| radius | soft / pill buttons | sharp | sharp, 2px borders |
| shadows | brand-tinted soft | ambient hairline | hard offset blocks |
| type | Vazirmatn 800 | Amiri headings | Lalezar + Tajawal |
| reveal | fade-up + parallax | line-mask, slow | none, instant |
| background | floating glows | flat + rules | visible grid |
| hero | split + mockup | centered stack | oversized type |
| services | card grid | numbered list | numbered list |
| sections | 8 | 9 (adds logos, process) | 8 (adds faq, comparison) |

Fonts are all Arabic-script capable. **A Latin display face renders tofu in
Persian** — check coverage before adding one.

## Theme tokens

`null` in `config/theme.php` is not a bug, it is an instruction: *"derive this"*.
`ThemeTokens` fills every null from the primitives via `color-mix()`, so six
colors produce a complete palette:

```php
'primary_hover' => "color-mix(in oklab, {$p} 85%, black)",
'text_muted'    => "color-mix(in oklab, {$ink} 62%, {$bg})",
'border'        => "color-mix(in oklab, {$ink} 8%, transparent)",
'on_primary'    => self::readableOn($p),   // WCAG black-or-white
```

Because `text_muted` derives from `text` **and** `background`, it is correct in
light mode too — previously it was two fixed greys that only worked on dark.

Groups: `colors`, `typography`, `shape`, `layout`, `spacing`, `landing`,
`effects`, `gradients`, `assets`, plus the new `surface`, `depth`, `scale`,
`motion`, `background`, `decoration`, `buttons`, `brand`, `i18n`,
`accessibility`, `schemes`, `custom`.

Three levers retune a whole group at once instead of restating it:
`shape.radius_scale` (sharp→pill), `scale.density` (compact→spacious),
`depth.elevation` (flat→heavy→neon→brutal).

### Values vs. behaviours

Lengths and colours become CSS custom properties, emitted by
`partials/theme-vars.blade.php` from `ThemeTokens::VARS` — a declarative map, so
adding a token means editing the config, not the partial. (The old hand-written
partial silently dropped `radius_2xl`, `sidebar_radius` and `stagger_ms`.)

Behaviours cannot be custom properties, so they become `data-*` attributes via
`partials/theme-attrs.blade.php` and the CSS keys off them:

```css
[data-reveal="line-mask"] .reveal { clip-path: inset(0 0 100% 0); }
[data-hover="glow"] .lp-card:hover { box-shadow: 0 0 0 1px var(--c-primary-hover); }
```

Adding a reveal style is therefore a CSS-only change — `landing.js` keeps
toggling `.is-visible` and never needs to know.

## Landing sections

`public.landing.sections` is an ordered list of names. Each name dispatches
through `sections/_dispatch.blade.php`:

```
sections/{name}/tenants/{slug}.blade.php   one-off for one tenant
sections/{name}/{variant}.blade.php        chosen by the archetype
sections/{name}/default.blade.php          platform default
```

A missing variant is not an error — it falls through. An archetype can name a
variant before it exists.

Shared primitives live in `sections/_heading`, `_button`, `_icon`,
`_glow-blobs` and `resources/css/landing.css` (`.lp-section`, `.lp-card`,
`.lp-btn`, …). Section templates no longer restate padding, radius, borders or
hover.

## Feature switches

Per-tenant now. Set `features` in `config/tenants/{slug}.php`, or in
`website_configs.layout_config` to avoid a deploy:

```php
'features' => ['blog_management' => false],
```

`EnsureConsultantFeature` reads through `site()`, so a disabled feature is
hidden and its route 404s **for that tenant only**.

## Testing

```powershell
php artisan test              # Unit + Feature
php artisan tenant:validate   # config schema, WCAG contrast, assets, templates
```

`tenant:validate` catches the failures that are otherwise invisible: a typo'd key
nothing reads, a palette nobody can read, a section name with no template, an
image path that 404s.

To assert a config change in a test without writing a file or a DB row:

```php
site_override(['theme' => ['colors' => ['primary' => '#123456']]]);
```

## Notes for a future Octane move

`SiteConfig` is bound with `$this->app->scoped()` and memoizes per slug, so it is
already Octane-safe. Two things are not:

- `IdentifyTenant` uses `app()->instance('tenant', …)` — fine per-request under
  Octane, but confirm the sandbox boundaries.
- Never mutate `config()` to apply tenant overrides. That was the earlier design
  and is why `site()` exists.
