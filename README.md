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

## Tenant icon sets

Set `theme.icons.set` in `config/tenants/{slug}.php`. For example:

```php
 'theme' => [
     'icons' => ['set' => 'lucide'],
 ],
```

The five selectable choices are `font-awesome` (legacy), `lucide`, `tabler`,
`bi` (Bootstrap Icons), and `la` (Line Awesome). The catalog contains exactly
four custom sets; `ThemeIcons::choices()` adds Font Awesome for the picker.
Existing Lucide and Tabler tenant configurations remain valid.
`font-awesome` preserves the original appearance and is the fallback for an
invalid or absent choice (including removed custom sets). Existing config/database/personal layer precedence
is unchanged; higher-layer overrides can supersede the tenant file.

The shared partial loads only the selected local stylesheet. CSS maps existing
Font Awesome classes to SVG masks, retaining colors, sizes, accessibility
attributes, and JavaScript class toggles without rewriting templates. Legacy
`.fa`, `.fas`, `.far`, `.fa-solid`, and `.fa-regular` classes are supported.
Explicitly listed brand marks and arbitrary runtime tenant icon values retain
Font Awesome; this fallback still uses the existing configurable CDN stylesheet
(`theme.assets.icon_library_url`). Static source concepts must map in all four
sets: an unknown source icon or incomplete mapping fails the build.
Inline SVG illustrations and emoji are intentionally not replaced.

### Adding an icon and running the coverage guard

1. Add the Font Awesome concept to `resources/icons/catalog.json` under `icons`,
   with explicit `lucide`, `tabler`, `bi`, and `la` concrete Iconify icon names.
   Check `node_modules/@iconify-json/{set}/icons.json` → `icons`, not `aliases`:
   upstream aliases can carry transforms and are deliberately rejected.
2. For a legacy spelling, add an `aliases` entry targeting a canonical concept.
   Preserve meaning (for example, `times-circle` targets `circle-xmark`, not
   plain `xmark`). There is no suffix guessing or silent per-set fallback.
3. Run the guard, regenerate, and run the focused tests:

   ```sh
   npm run icons:audit
   npm run test:icons
   npm run icons:build
   php artisan test --filter=ThemeIconsTest
   ```

The build includes the same guard before writing assets. The audit scans PHP,
Blade, JS/TS, HTML and related source files in `resources/views`, `resources/js`,
`config`, and `app`, including isolated `fa-*` strings used by class toggles.
It explicitly excludes FA utilities, the listed FA brands, Persian date hooks
(`fa-date`, `fa-weekday`), and the known RTL arrow template fragment (both arrow
outcomes are still required). New brands need an explicit audit allowlist entry;
unknown or misspelled tokens fail with their source locations. Runtime values
stored only in the database cannot be statically audited.

`npm run test:icons` regression-checks detection of new used concepts, missing
mappings in each of the four sets, invalid concrete names, and broken aliases.
`public/icons/manifest.json` records exact mappings with empty fallback lists.
`resources/icons/preview-manifest.json` preserves `{sets: {id: {semantic: dataURI}}}`
for all mapped names; picker previews use masks colored by `currentColor`, not
fixed-color images. Only the four custom Iconify packages are dependencies.
The build uses locked development packages; no runtime Iconify API is called.
Bundled attribution links are in `public/icons/NOTICE.md`. Serve SVG data URLs
under your CSP's `img-src` policy if you use a restrictive CSP.

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

## Local hierarchy demo setup

**Local/testing only; never point these commands at a live database.** The base
schema comes from `database dump/sapienstech_db_final.sql`, not initial Laravel
migrations. Use an already restored, disposable local database with its migration
history intact. Back it up first; do not use `migrate:fresh` or reset existing data.
The owner/teacher-role and profile/domain migrations must be applied before the
hierarchy seeder. Review pending migrations before running them:

```sh
php artisan migrate:status
php artisan migrate
php artisan db:seed --class=HierarchyDemoSeeder
```

Set `APP_ENV=local` (or `testing`). Optionally set
`HIERARCHY_DEMO_PASSWORD` in your local environment **before the first seed**
(minimum 12 characters). The local-only default is `HierarchyDemo!2026`.
If configuration was cached, clear it locally so environment changes take effect.
The seeder refuses all other environments, including production even with
`--force`. Environment guards cannot detect a live database misconfigured as
local: verify your connection yourself. These commands are setup instructions,
not permission for an agent to migrate or seed a live database.

### Schema and relationships

- `tenants.hierarchy_type`: nullable string; `school` for Moein and `consultancy`
  for Salam. Existing tenants are left null by the migration; existing owners
  are untouched.
- `classrooms`: `id`, `tenant_id`, `grade` (50 characters), `name` (100 characters),
  timestamps; unique `(tenant_id, grade, name)` and `(tenant_id, id)`.
- `classroom_student`: `tenant_id`, `classroom_id`, `student_id`.
- `classroom_teacher`: `tenant_id`, `classroom_id`, `user_id`, required `subject`
  (100 characters). One teacher entry per classroom; subject is pivot metadata.
- `student_staff`: `tenant_id`, `student_id`, `user_id`, for direct consultant or
  individual-teacher assignments, independent of classrooms.

Each pivot has a composite primary key over its three IDs and composite foreign
keys `(tenant_id, related_id)` referencing `(tenant_id, id)` on **both** related
parents, with cascading deletes. The migration adds the requisite unique keys to
`users` and `students`. This prevents cross-tenant links even via direct SQL on
MySQL or SQLite (SQLite foreign-key enforcement must remain enabled). Pivots have
no surrogate IDs or timestamps; supply `tenant_id` on every attachment, and
`subject` for teacher attachments. `Classroom::students()` and `teachers()` expose
these pivot fields. No custom pivot model is required. Authorization and role
checks remain the responsibility of application policies/controllers; the schema
does not restrict `user_id` to a particular role or forbid a student's membership
in more than one classroom.

### Demo cohorts and local sign-in

| Tenant | Demo staff | Demo students and assignments |
|---|---|---|
| Moein (`moein`, school) | Principal مریم فرهمند + 9 subject teachers | Exactly 45 demo students; 9 classrooms: هفتم / هشتم / نهم × الف / ب / ج, 5 per classroom |
| Salam (`salam`, consultancy) | Admin آرزو بهرامی + 3 consultants | 12 demo students, 4 each in دهم / یازدهم / دوازدهم; no classrooms created |

Moein staff emails:

- `hierarchy.principal@moein.test` — principal (`tenant_admin`)
- `hierarchy.math@moein.test` — علی رضایی, ریاضی
- `hierarchy.science@moein.test` — نرگس احمدی, علوم تجربی
- `hierarchy.persian@moein.test` — رضا کریمی, فارسی
- `hierarchy.english@moein.test` — سارا محمدی, زبان انگلیسی
- `hierarchy.arabic@moein.test` — حسین موسوی, عربی
- `hierarchy.social@moein.test` — الهام نادری, مطالعات اجتماعی
- `hierarchy.physics@moein.test` — آرش کاظمی, فیزیک
- `hierarchy.biology@moein.test` — مهسا شریفی, زیست‌شناسی
- `hierarchy.chemistry@moein.test` — پیمان توکلی, شیمی

Every teacher covers two classes in each grade (6 classrooms / 30 demo students),
with six subject teachers per classroom: 54 teacher-class links and 45 cohort
memberships. Students are `hierarchy.student01@moein.test` through
`hierarchy.student45@moein.test`, assigned in grade/class order above, five at a
time. Each student retains one cohort classroom, keeping exactly five students
per classroom. Shared subject instruction is represented through teachers linked
to multiple cohort classrooms, not extra pupil memberships or subject classrooms.
Physics, biology and chemistry activities are appropriate to middle-school science.
No direct `student_staff` links are created for Moein.

Salam staff emails:

- `hierarchy.admin@salam.test` — admin
- `hierarchy.nazari@salam.test` — بهاره نظری
- `hierarchy.karimi@salam.test` — امیرحسین کریمی
- `hierarchy.rahimi@salam.test` — سپیده رحیمی

Salam students are `hierarchy.student01@salam.test` through
`hierarchy.student12@salam.test`, in grade order above. Primary consultants cycle
Nazari → Karimi → Rahimi. Students 10, 11, 12 additionally share with Karimi,
Rahimi, Nazari respectively: 15 direct links total, each consultant sees 3
individual + 2 shared demo students. Profiles have varied Persian names, bios,
interests and learning preferences; middle-school majors are null, while senior
students vary among ریاضی و فیزیک / علوم تجربی / علوم انسانی.

All **new** demo accounts use the configured common password. Existing matching
accounts retain their password and profile; incompatible roles, grades or pinned
domains abort the transaction instead of changing the account. Reruns add missing
rows/links without deleting existing data or resetting passwords. Existing owners
remain owners; the demo principal/admin becomes owner only if none exists.
Counts above describe the demo cohort, not pre-existing tenant data. Each student
gets two mock schedule records (114 total), matched by tenant/student/title so
reruns do not accumulate weeks or overwrite user edits. No exams or scores are
fabricated. Moein enables teacher/student teaching features; Salam disables them
and retains consultation features. Higher-priority stored theme settings can
still override these tenant config files.

Add these hosts manually to your local hosts file (Windows:
`C:\Windows\System32\drivers\etc\hosts`, as Administrator):

```text
127.0.0.1 moeinacademy.test salamacademy.test
```

Open `http://moeinacademy.test:8000` and `http://salamacademy.test:8000` on your local
server. Moein's existing primary domain was verified as `moeinacademy.test`; the
seeder reuses its actual existing domain if different and prints the chosen
hosts. It creates `salamacademy.test` for آکادمی سلام, refusing to take a domain
owned by another tenant. Keep sessions host-only; add both hosts to
`VITE_ALLOWED_HOSTS` when using Vite. Never deploy these demo credentials or use
them for real accounts.

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

`blocks` is the exception to file-owned content: an optional section the
tenant composes from typed rows (heading, text, button, card, image, spacer,
divider) in the Theme Studio. The studio's list control renders only the
cells each row's type owns, validation is per row type, and normalization
drops everything else — so a forged or stale key can never reach the stored
layer.

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

## Local fonts

Fonts are served from this application's origin, not Google Fonts or `ASSET_URL`.
Install appropriately licensed files in directories under `public/fonts/`
(the current local installation has `vazirmatn`). Font binaries are deployment
assets; ensure they are present on each server, including fresh clones.

The default expects `public/fonts/vazirmatn/Vazirmatn[wght].woff2` (Vazirmatn's
variable face, weights 100–900). Until installed, browsers use the configured
fallback stack; the missing font request may return 404. No build is needed for
font binaries. Rebuild config caches after changing PHP configuration, but adding
or removing font directories/files needs neither a build nor a config-cache refresh.

### Studio directory choices

All five Studio font roles (body, heading, accent, button, mono) use a dropdown,
not free text. New selections are exact immediate directory names under
`public/fonts/`: ASCII letters/numbers followed by letters/numbers, `_` or `-`.
Only directories with recognized local font files are offered; empty directories,
unsupported files, unknown filenames without metadata, and symlinked directories
are not choices. Validation reads disk at runtime and rejects arbitrary families,
paths and newly selected missing directories.

Each selected directory maps to a CSS family alias `StudioFont-{directory}` and
loads its faces through the shared font partial, independently for every role.
Selecting the same directory for several roles emits its faces only once. Font
changes reload the live-preview iframe so new `@font-face` declarations load too.

The picker explicitly offers reset/inherit (empty value) and, when necessary,
**retain current value** for a legacy configured stack or a removed directory.
An unrelated save therefore does not silently replace existing typography with
the first installed font. Only the exact current legacy value remains accepted;
Studio cannot introduce new free-text stacks. PHP configuration may still use
family stacks and explicit faces as below.

### File discovery and descriptors

Place files directly inside the font directory using a recognized weight suffix,
for example `Example-Regular.woff2`, `Example-BoldItalic.ttf`, `Example-700.woff`,
or `Example-SemiBold-Italic.otf`. Suffixes are case-insensitive: Thin (100),
ExtraLight/UltraLight (200), Light (300), Regular/Normal (400), Medium (500),
SemiBold/DemiBold (600), Bold (700), ExtraBold/UltraBold (800), Black/Heavy (900),
and numeric hundreds through 1000. Italic/Oblique suffixes select the style;
a standalone `Example-Italic` means regular italic. A suffix must start the
filename or follow `-`, `_`, or a space.

Bare family names and variable-font filenames (including `[wght]`) do **not**
guess a weight or range. Declare their real descriptors in `faces`; explicit
metadata takes precedence over filename inference. Existing configured faces
retain their original families and descriptors, and installed files in a selected
directory also receive its alias. Configured files in nested subdirectories can
be aliased, but automatic filename discovery is limited to immediate files.

Set roles and faces under `theme.typography` in `config/theme.php` or a tenant's
`config/tenants/{slug}.php`, for example:

```php
'font_family' => 'Vazirmatn, sans-serif',
'font_heading' => 'My Heading, serif',
'font_button' => 'Vazirmatn, sans-serif',
'font_accent' => 'My Heading, serif',
'font_mono' => 'ui-monospace, monospace',
'faces' => [
    [
        'family' => 'Vazirmatn',
        'src' => '/fonts/vazirmatn/Vazirmatn[wght].woff2',
        'weight' => '100 900',
    ],
    [
        'family' => 'My Heading',
        'src' => '/fonts/my-heading/Bold.woff2',
        'weight' => '700',
        'style' => 'normal',
        'display' => 'swap',
    ],
],
```

`faces` lists replace inherited lists wholesale; include every required face.
Use `faces => []` with system font roles to disable font loading. The `moein`
tenant demonstrates local Amiri regular/bold plus Vazirmatn; those Amiri files
must also be installed manually. Archetype font names still apply, but their
legacy `theme.assets.font_url` settings are no longer read by active layouts.
For other archetypes, supply local faces for their named fonts or override the
roles with installed/system fonts. Check Persian glyph coverage and licensing.

Each face requires one `family` name and a root-relative `/fonts/...` `src`.
Supported extensions are `.woff2`, `.woff`, `.ttf`, and `.otf`; `format` is inferred
(`woff2`, `woff`, `truetype`, `opentype`) and, if supplied, must match. Weight is
1–1000, an ascending variable range, `normal`, or `bold` (default `400`). Style is
`normal`, `italic`, or `oblique`; display defaults to `swap`. Add separate entries
for static weights and italic files. Paths accept ASCII letters, numbers, spaces,
underscores, hyphens, periods and brackets; unsafe paths, remote URLs, traversal,
query strings, unsupported formats and invalid descriptors are skipped. Filenames
are URL-encoded automatically; supply the literal filename, not percent escapes.

Existing role keys retain their meanings: `font_family` → `--font-body`,
`font_heading` → `--font-heading`, plus `font_button`, `font_accent`, and
`font_mono`. Families may be comma-separated stacks; family names are safely
quoted and generic fallbacks retained. Body, headings, buttons and code use these
tokens sitewide; accent remains available to components via `--font-accent`.
Null heading/button/accent roles inherit the body font. Font configuration is
read through the existing layered `site()` resolution, without a new upload API
or accepting arbitrary font text through Studio.

`partials.theme-vars` includes `partials.theme-fonts` once per active shell
(consultant, student, teacher, public, landing, blog and login). The legacy public
shell requests fonts only, preserving its existing palette. `ThemeFonts` validates
values before raw CSS output. Static HTML mockups under `resources/views/Public`
are not active Blade layouts and are outside this migration.

Font regression tests: `tests/Unit/ThemeFontsTest.php` and
`tests/Feature/ThemeFontsTest.php`.
