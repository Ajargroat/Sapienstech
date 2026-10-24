# Sapienstech — Project Soul & Change Guide

> **Purpose:** preserve the reasons behind the architecture so a coding agent can make a targeted change without re-reading the entire repository. This is a compact mental model and navigation map, not a substitute for the implementation, tests, or the focused documents named below.
>
> **Reviewed against:** `Ajargroat/Sapienstech`, branch `main`, on 2026-10-24. Update this file when a change alters an invariant or architectural boundary. If this file conflicts with current executable code or a focused spec/test, verify the discrepancy and trust the implementation plus its tests; then correct this file.

## 1. What this product is

Sapienstech is one Laravel application serving multiple Persian-language education and Konkour-consultancy websites. Each tenant can have its own domain, brand, landing-page structure, feature set, roster, and tenant-admin edits without becoming a separate codebase or deployment. The product also has school-style hierarchy: the same platform can serve a consultancy or a school with teachers, classrooms, and assigned students.

The key distinction is that **tenant identity, data access, feature availability, and presentation are four separate concerns**:

- **Tenant identity:** which tenant this request belongs to, resolved from the request host.
- **Data access:** which records the current tenant and actor are permitted to read or change.
- **Feature availability:** which product surfaces this tenant has enabled.
- **Presentation:** which configuration, archetype, content, and personal overrides shape the page.

Do not use one of these as a substitute for another. A hidden navigation item is not authorization; a theme setting is not tenant identity; a feature flag is not a data-isolation boundary.

There is also a separate company marketing site at `sapienstech.ir`. It is **not a tenant** and is intentionally served outside Laravel; see §7.

## 2. Request identity and security boundaries

### 2.1 Tenant resolution is host-driven

The expected request path is:

`Host header → Domain::normalize() → IdentifyTenant → tenant()/domain() → tenant-aware middleware, routes, models and site()`

`App\Http\Middleware\IdentifyTenant` normalizes the host, looks up the `domains` table, caches the mapping using `Domain::cacheKey()` (currently 600 seconds), and binds both the tenant and domain for the rest of the request. An unknown host is a 404; a suspended tenant is a 403. `tenant()` and `domain()` are the canonical helpers after this stage.

**Tenant identity comes from the host, never from a form field, URL parameter, or other client-supplied tenant ID.** The only deliberate exception is `TENANT_FALLBACK_DOMAINS`, a development convenience that maps approved local hosts to the primary tenant. It must be empty in production: a public host in this list could be served as the wrong tenant.

The web middleware order in `bootstrap/app.php` is intentional:

1. `IdentifyTenant` resolves the tenant.
2. `EnsureUserDomain` checks whether the authenticated staff account may enter through this domain.
3. `EnsureHierarchyAccess` enforces panel and hierarchy boundaries.
4. `ApplyPersonalTheme` overlays the current user's personal preferences after the session is available.

`SecurityHeaders` is appended globally as a platform-wide floor, not merely to one portal.

Sessions must remain **host-only**. Do not set `SESSION_DOMAIN` to a shared parent domain to make cross-tenant login appear to work; that defeats the isolation the host model is designed to provide.

### 2.2 Tenant scoping is enforced at the data layer

`App\Models\Concerns\BelongsToTenant` adds an Eloquent global scope to models that have `tenant_id`, and stamps the current tenant on creates when the tenant is already bound. This is the normal rule for tenant-owned records. A query such as `Student::find($id)` should not be treated as safe merely because a controller was reached through a tenant URL: understand the model's scope and the actor's permitted roster first.

There is one explicit, important exception: `SharedAcrossTenants` supports intentionally shared pool rows (`tenant_id = NULL`) as well as tenant-specific rows. It is used for the question bank. A shared row is a conscious data-model decision, not a reason to remove tenant scoping from other models. When debugging data visibility, inspect the model concern and any explicit scope overrides before changing a query.

For school/consultancy hierarchy, tenant isolation still applies, and `StudentAccess` adds a second question: **which students may this staff member see?** Owners may see the tenant roster; other staff access directly assigned students, and school teachers also reach students through their classroom links. The classroom pivots include `tenant_id`; composite keys and foreign keys are intentional protection against cross-tenant relationships even through direct SQL.

A student's removal is a **soft roster removal**, not destruction of the person. `Student`'s `roster` scope hides removed students from ordinary lists and also prevents their next authenticated request from resolving the student account. Deals, attempts, and chat history can survive. Code that needs historical context must opt out of that scope explicitly and narrowly; do not globally make removed students visible again.

### 2.3 Roles and guards are not interchangeable

- `web` guard / `App\Models\User`: tenant admins, consultant staff, and teachers.
- `student` guard / `App\Models\Student`: the student portal.
- Teacher access is a role on a `User`, not a third guard. Teachers have a dedicated panel and must not gain consultant-only access by entering `/consultant/*`.
- `tenant_admin`, `consultant_staff`, `teacher`, and `platform_admin` represent different responsibilities. Hierarchy tenants add owner rules, where settings/blog and Theme Studio are owner-only.
- `User::canRunOps()` is deliberately not identical to `isPlatformAdmin()`: the internal operations console may also be granted to a tenant admin through the separate `is_platform_admin` capability flag while retaining their tenant-panel identity.

Be explicit about the guard and actor type in middleware, services, channel authorization, and tests. Laravel's default guard is not sufficient for every surface because a browser session can involve both a student actor and a web user. Code such as `ChatService` and `LessonAccess` intentionally checks the explicit actor and fails closed.

## 3. The domain rules that should not drift

### Student enrolment and identity

`App\Support\StudentEnrollment` is the single domain service through which a person becomes a roster student. The public join flow creates a **pending request**, not a student; approval creates the student after the staff member verifies the declared payment. Consultant hand-entry reaches the same service so it cannot develop different account-creation rules. The flow uses transactions/row locks to prevent two concurrent approvals from creating two accounts from one request.

An active roster match on any of phone, national code, or email is refused to avoid duplicate rows and account takeover. Removed students can be reused/restored. Re-submitting an open join request updates the existing request instead of creating a queue of duplicates. Consultation requests are a separate workflow: they mean “contact me,” and approving/rejecting them changes request state rather than creating or deleting a student.

`IranianIdentity` is the canonical normalization layer for Persian/Arabic-Indic digits, Iranian mobile numbers, and national codes. `LoginIdentifier` chooses email, phone, or national code from one `identifier` field and always scopes lookup to the current tenant. Keep normalization before validation and preserve generic login failures so the login surface does not leak account state. The two guards and their uniqueness rules are deliberately distinct; a national code is checked across both tables because one person should not acquire two identities.

**Primary references:** `docs/identity.md`, `app/Support/IranianIdentity.php`, `app/Support/LoginIdentifier.php`, `app/Support/StudentEnrollment.php`.

### Exams and question bank

`Question`/`Answer` use a deliberately shared question bank so different tenant academies can build exams from one imported pool. `SharedAcrossTenants` is what makes `tenant_id = NULL` visible as a shared row. Do not replace that model with tenant-owned questions or disable scoping on unrelated academic data.

`App\Support\ExamGrader` is the canonical grading computation. It is pure computation; endpoints persist the result rather than inventing their own grading rules. A question without a stored correct answer is **ungraded**, stays visible/selectable, and does not enter the score denominator. Preserve consistency between the denominator, percentage, raw score, simple score, and negative-marking score. A chosen answer must belong to the question being answered.

Releasing an AI-generated question writes the question and its answer rows in one transaction and links it to its lesson while keeping it in the shared bank. A partially written question could otherwise be silently considered ungraded or be unavailable in one of the two readers. See `LessonQuestionRelease` and the exam/report contract tests before changing this path.

### Lessons, PDF ingestion and AI-generated content

The lesson library separates **platform ingestion** from **tenant teaching surfaces**:

- `/ops/*` is our platform operations console, protected by `ops.access`; it is not a tenant feature and does not depend on a teacher role or a tenant's lesson flag.
- Staff lesson routes are mounted under both consultant and teacher panels, but the shared `LessonAccess` class decides whether the current actor is allowed in the panel they actually entered.
- The operations workflow must reach a publishable lesson and releasable questions, not stop at a draft invisible to students.

The PDF extractor is behind the `PdfExtractor` interface so tests can fake extraction without Python/PyMuPDF. The real implementation calls `scripts/pdf/extract.py` using an argument array (not a shell-built command) and exchanges result JSON through files so Persian text is not corrupted by Windows console encoding. Failures should become explicit extraction results/failed states, not silent stuck “queued” states. The operations flow currently runs its heavy steps inline in the request; do not casually reintroduce queue/polling assumptions without solving the original reliability failure.

AI is a drafter, not an unquestioned authority. `LessonDrafter` builds prompts from the lesson's own extracted passage, calls the AI abstraction, and validates the result. `LessonQuestionValidator` rejects malformed, duplicate, ungrounded, or insufficiently explained questions rather than “repairing” them into plausible content. A source quote must actually occur in the lesson. If no valid question survives, nothing should be written. Feature switches control AI draft and question-release capabilities; preserve their default-off behavior unless a tenant explicitly enables them.

**Primary references:** `routes/ops.php`, `app/Http/Controllers/Ops/LessonOpsController.php`, `app/Support/LessonPdf/`, `app/Support/Lessons/`, `app/Support/Ai/`, `tests/Feature/Lessons/`.

### Chat, deals and scheduling

`ChatService` is the shared domain implementation for consultant and student chat. Every operation takes an explicit `ChatActor` and checks membership itself, because the default Laravel Gate/guard is not enough for the dual-guard model. Its two shapes are deliberate: a staff member plus one student for direct chat, or a staff-owned group with multiple students. There is no ownerless student-to-student conversation. Private broadcast channels in `routes/channels.php` must enforce the same effective membership rule as the HTTP service; never widen websocket access beyond endpoint access.

`DealService` is the transactional state machine for student renewal periods. Keep the invariants in the service, not only in UI controls: decisions open only near/after period end and only once, payment amount comes from the deal rather than client input, only the tenant owner reviews a payment, approving a payment atomically opens the next period, and withdrawing does not remove access during a paid period.

Schedule assistance, reports/report cards, assignments, and classroom timetable flows each have domain support classes and panel-specific controllers. Prefer the existing service and access boundary over copying business rules into a new controller or JavaScript bundle.

## 4. Tenant configuration and design system

### 4.1 `site()` is the tenant-aware read API

Read tenant presentation and feature settings through `site()` / `App\Support\SiteConfig`, not by mutating Laravel's global `config()` at runtime. The conceptual merge order is:

1. `config/theme.php` — platform baseline.
2. `config/archetypes/{name}.php` — visual and structural identity bundle.
3. `config/tenants/{slug}.php` — sparse, deployment-reviewed tenant differences.
4. `website_configs.layout_config` — tenant-admin runtime edits.
5. request-local override used by tests/previews.
6. per-user personal theme preferences.

Later applicable layers win. `SiteConfig` is scoped and memoizes per tenant; its separate personal layer is replaced per request so one user's theme cannot leak into another request. A tenant file should usually contain only the values it changes, not a copy of the full baseline.

`Merge::structural()` recurses through associative arrays but **replaces lists wholesale**. Do not swap it for `array_replace_recursive()`: list merging by numeric index can leave old landing sections or list items behind after an override.

### 4.2 Archetypes and tokens are executable design decisions

An archetype changes layout and section structure as well as typography, materials, motion, shapes, and colors. It exists because color-only “presets” did not actually change what users saw and made tenant sites look like the same template.

`ThemeTokens` derives values left `null` in the baseline from a small set of primitives. `null` generally means “derive this,” not “missing configuration.” `ThemeTokens::VARS` is the explicit map from token paths to CSS custom properties, emitted by `resources/views/partials/theme-vars.blade.php`. Behavioral choices travel through `data-*` attributes emitted by `theme-attrs.blade.php`, and CSS responds to those attributes. If a new token is not in the explicit variable map, it does not reach the browser.

When a token or archetype changes, inspect both the resolved configuration and its actual consumers. Run `php artisan tenant:validate` to catch unknown config keys, missing section templates/assets, and insufficient contrast.

### 4.3 Theme Studio is a security boundary

`config/studio.php` is not merely a UI form description. It is the whitelist of paths a tenant may read/write through Theme Studio, with validation rules for every value that could reach CSS or Blade. `StudioSchema` builds generic form rules from it; `StudioStyles`/related normalization constrain canvas node payloads. The Studio stores **sparse diffs**, so resetting a value removes the override and lets the archetype/tenant/baseline show through.

Do not expose raw CSS, arbitrary URLs, unknown keys, or unvalidated values to tenant-editable controls. Derived tokens should not become separately editable: that creates two conflicting sources of truth. File-owned complex content should stay file-owned until the corresponding editor and validation model can represent it safely. If a new control is intended, update the whitelist and its tests, not just the Blade form.

### 4.4 Landing pages are data contracts, not arbitrary templates

`LandingSurface` chooses the layout/surface; `LandingFeatures` defines DB-backed landing data and the only composer path for those features. These are separate decisions. A bespoke tenant landing replaces the layout choice, not the tenant's data feed.

The landing contract has three data classes:

- **A — config-only:** copy/sections/stats come from the `site()` configuration chain.
- **B — DB-backed public read:** for example published blog posts are supplied by a tenant-scoped composer and normalized into the same item shape as config content.
- **C — DB-backed public submission:** visitor posts such as join and consultation requests.

A data-backed section with no items should disappear rather than render an empty heading. Do not invent tenant-specific copy for a configuration key: the platform supplies structure and the tenant supplies content. Public submissions need a named route, a dedicated rate limiter, a FormRequest, tenant identity from the host, deduplication, triage, and tests proving the row lands in the right tenant. A pending join request is not yet a student.

Section dispatch is ordered by `public.landing.sections` and falls through from tenant-specific template to archetype variant to platform default. Blocks are the exception to file-owned content: they are typed rows normalized and validated by the Studio rather than free-form arbitrary HTML.

**Primary references:** `docs/landing-data.md`, `app/Support/LandingSurface.php`, `app/Support/LandingFeatures.php`, `app/Support/LandingTokens.php`, `app/Console/Commands/TenantValidate.php`.

### 4.5 Fonts, icons and local assets

Persian typography must use fonts with Arabic-script glyph coverage. Fonts are served locally from `public/fonts/`; do not silently introduce a Latin-only display face or assume Google Fonts/remote assets are available. `ThemeFonts` only exposes recognized local files/descriptors to the Studio font selector.

The icon build is an offline, explicit mapping pipeline. `resources/icons/catalog.json` maps semantic source concepts to concrete Lucide, Tabler, Bootstrap Icons (`bi`), and Line Awesome (`la`) icon names. The build/audit scripts reject unknown or incomplete mappings rather than guessing icon suffixes or silently falling back. Font Awesome is retained as the legacy/fallback path for supported cases. To add an icon, update the catalog and run:

```sh
npm run icons:audit
npm run test:icons
npm run icons:build
php artisan test --filter=ThemeIconsTest
```

## 5. Frontend runtime contracts

Blade is the application shell. The codebase is **not a single-page React application**: most screens remain server-rendered Blade with focused JavaScript feature bundles.

`resources/js/page-router.js` keeps the consultant shell alive between eligible internal page navigations, initializes each screen's feature entry, calls cleanup when needed, and selectively swaps `data-router-region` fragments for query-only updates. A feature entry's contract is `export default function init(region)` with an optional cleanup return. Links outside its intended consultant scope, pages that opt out, and responses without the expected shell must continue to use real navigation.

`vite.config.js` explicitly lists Laravel/Vite entrypoints. A JS file used by a Blade view but missing from this list will not land in the manifest and can make the view fail at render time. When adding or moving a feature entry, update the Vite list and check the route/view that consumes it. `public/demo/` contains intentional static demo pages that nginx can serve directly without Laravel.

React is used selectively as **islands inside existing Blade regions**; `resources/js/react/mount.js` preserves the same `init(region)`/cleanup contract. Islands do not own the page skeleton and must share the semantic CSS tokens emitted for Blade (`--c-*`, `--radius-*`, `--font-*`) or the roles in `resources/js/react/tokens.js`. Avoid a broad framework rewrite for a small UI change and avoid hard-coded colors in an island.

For front-end changes, follow `docs/frontend-spec.md`: specify the exact existing view/pattern, measurable layout values, current vs. desired behavior, acceptance surface, and explicit out-of-scope areas. Text and named references beat a vague screenshot for repeatable implementation.

## 6. Operations, deployment and persistence cautions

### Separate corporate site

`corporate-site/index.html`, `corporate-site/api.php`, local fonts/assets, and `corporate-site/config.php` form the company site. It is served from its own nginx document root, `corporate-site/`, while tenant requests use `public/` and Laravel's front controller. `api.php` is a small standalone PDO endpoint and writes leads to the app database; it does not boot Laravel. Keep credentials outside the web root and do not commit `corporate-config.php`.

Do not move the corporate site into `public/` or route its ordinary page requests through `public/index.php` as a shortcut. The relative assets/API paths and host ownership assume the separate root. Duplicate nginx `server_name` blocks have previously caused the wrong root to win silently. Follow `docs/corporate-site.md` and `docs/deployment.md` for host changes.

### Database baseline

`database/migrations/2026_08_01_000000_create_base_schema.php` exists to create tables that originally predated Laravel migrations, so SQLite tests and fresh test schemas can build those tables. It creates only missing tables, then later migrations adjust the schema. Some production MySQL constraints are enforced by application code and are not recreated as SQLite CHECK constraints.

The README refers to a manually created schema dump under `database dump/`, but that dump was not present in the tracked tree inspected for this file. Do not assume a pristine local database is equivalent to production merely because migrations run. Inspect the actual DB schema and `php artisan migrate:status` before proposing schema changes; use a disposable database for demos. Never run `migrate:fresh`, truncate, or reset a valuable/live database as a convenience.

The hierarchy demo seeder is explicitly local/testing-only; its demo credentials must never be deployed. A migration or seeder is not permission to change production data.

### Build, cache and runtime

- Production Laravel document root: repository `public/`; `public/build` is generated and not committed.
- Standard front-end production build: `npm ci` then `npm run build` (the build first runs the icon guard/build, then Vite).
- The deploy runbook includes Composer install, migrations, config/route/view cache refresh, queue restart and supervised Reverb. Existing workers need restarting after code changes.
- `config:cache` freezes environment-derived configuration; after `.env` changes, rebuild it. Read `env()` through config files rather than assuming it is available at runtime.
- For multi-domain local Vite development, `VITE_ALLOWED_HOSTS`, `VITE_DEV_ORIGIN`, CORS origins and the app port are connected. A Vite 403 on all tenant assets may be host/origin configuration, not a Blade/CSS regression.
- `routes/console.php` defines scheduled chat auto-close and deal-reminder commands; don't overlook scheduler/runtime setup when modifying those workflows.

## 7. Fast navigation: change type → inspect only this chain

| Change or question | Start here | Then verify |
|---|---|---|
| Tenant routing/domain returns 404 or wrong site | `docs/ROUTING.md`, `IdentifyTenant`, `Domain`, `config/tenancy.php` | `TenantIsolationTest`, `DomainAccessTest`, `TenantMakeTest` |
| Tenant data visible to wrong actor | model + `BelongsToTenant` / `StudentAccess` / relevant policy/service | matching authorization test; inspect every `withoutGlobalScope()` |
| Login format or identity uniqueness | `docs/identity.md`, `IranianIdentity`, `LoginIdentifier`, `StudentEnrollment` | `LoginIdentifierTest`, `IdentityUniquenessTest`, enrolment tests |
| Theme token or visual mismatch | `SiteConfig`, `Merge`, `ThemeTokens`, theme partials, `config/theme.php` | `TenantThemingTest`, `ThemeTokensTest`, `tenant:validate` |
| Theme Studio field or canvas node | `config/studio.php`, `StudioSchema`, `StudioStyles`, Studio controller | `tests/Feature/Studio/`, `StudioDraftTest` / `StudioIsolationTest` |
| Landing section, CTA, blog feed or public form | `docs/landing-data.md`, `LandingSurface`, `LandingFeatures`, the target section/route | `LandingSurfaceTest`, `LandingDataInjectionTest`, `LandingLintTest`, submission/throttle tests |
| Consultant page JS does not initialize | Blade view + `resources/js/page-router.js` + its `resources/js/features/*.js` entry | `vite.config.js`, Node tests, relevant feature test |
| Student/teacher panel or roster behavior | panel controller + `StudentAccess` + model scopes + hierarchy middleware | corresponding Student/Teacher/Hierarchy tests |
| Exam scoring or a question missing from grading | `ExamGrader`, `Question`, `Answer`, question-bank scope | `ExamReportContractTest`, `StudentExamRunnerTest`, `QuestionBankPoolTest` |
| Chat access/broadcast/realtime | `ChatActor`, `ChatService`, `routes/channels.php`, and chat models/events | `ChatRelationAuthorizationTest`, chat feature tests, `ChatBroadcastChannelsTest` |
| Deal renewal/payment or reminder | `DealService`, `StudentDeal`, `DealPayment`, `DealController`, scheduled command | `StudentDealTest`, consultant deal tests, scheduler path |
| PDF extraction, AI lesson drafts or question release | `routes/ops.php`, `LessonOpsController`, `PdfExtractor`, `LessonDrafter`, release/validator classes | `tests/Feature/Lessons/`, console/ops and pipeline wiring tests |
| Company marketing site or lead API | `docs/corporate-site.md`, `corporate-site/*`, `deploy/nginx/sapienstech.ir.conf` | host/root and lead endpoint checks in the runbook |
| Icon renders as blank or unsupported | `resources/icons/catalog.json`, audit/build scripts, `ThemeIcons` | icon self-test + `ThemeIconsTest` |
| Deployment-only failure | `docs/deployment.md`, `.env.example`, `vite.config.js`, nginx config | identify build/cache/worker/host root before changing app logic |

## 8. Rules for future coding agents

1. **Use this file as the context cache.** Start with the relevant row in §7 and read only that dependency chain plus its tests. Do not scan all ~1,700 tracked paths or re-read the whole codebase for a narrow task.
2. **Find the canonical rule before editing.** Business invariants belong in the existing support service/model/access class, not duplicated in a controller, Blade template, or JS handler. Tests that assert a contract often exist because a previous implementation silently broke it; read their intent before loosening them.
3. **Treat tenant isolation as a default-deny system.** Verify the resolved tenant, guard/actor, model scope, ownership/roster access, feature flag, and any broadcast authorization separately. Do not trust client tenant IDs, hidden controls, or route prefixes by themselves.
4. **Make configuration changes at the correct layer.** Use `site()` for resolved tenant configuration; use `config/studio.php` to expose a validated tenant-editable setting; update `ThemeTokens::VARS` when adding a CSS token; use a request override for tests/previews rather than mutating global config.
5. **Preserve the relevant contracts.** For new JS entrypoints, keep `init(region)`/cleanup and update Vite's input list. For new landing submissions, follow the class-C checklist in `docs/landing-data.md`. For AI-generated correctness artifacts, validate and refuse bad content rather than silently repairing or publishing it. For shared question-bank rows, intentionally set `tenant_id = NULL` and write related answers atomically where the workflow requires it.
6. **Prove behavior with focused tests first.** Run the smallest relevant PHPUnit/Node tests, then broader tests when the risk warrants it. For tenant configuration, run `php artisan tenant:validate`. For icons, run the audit/self-test/build guard. Do not claim a test passed unless it was actually executed.
7. **Keep changes local and explain scope.** Avoid broad refactors, directory moves, framework conversions, copied business logic, or cleanup unrelated to the requested behavior. State which architectural boundary the change touches and which tests cover it.
8. **Do not perform destructive environment work by inference.** Never reset a database, run local demo seeders against a live DB, change production nginx, publish demo credentials, or assume a missing external runtime is present without first checking the environment and its state.
9. **Update this file when a decision changes.** It should stay a compact record of why boundaries exist, not a changelog of every implementation detail. Prefer links to source/tests over duplicating code or long lists of fields.

## 9. Focused documents worth reading before the relevant change

- `README.md` — local start, tenant setup, config/archetype/theme contracts, icon pipeline, test commands, and known runtime notes.
- `docs/ROUTING.md` — host resolution, middleware order, auth guards, route naming and feature gates.
- `docs/identity.md` — normalization, login lookup, uniqueness, and enrolment identity behavior.
- `docs/landing-data.md` — landing data classes, composer contract, forms, throttling, deduplication, and documented gaps.
- `docs/frontend-spec.md` — how to specify measurable front-end changes for an AI coding agent.
- `docs/deployment.md` — Ubuntu deploy process, Laravel document root, Vite manifest, caches, workers, and Reverb.
- `docs/corporate-site.md` — separate company-site vhost, API, credentials, deployment, and rollback.
