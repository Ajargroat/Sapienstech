# Routing

How an incoming request becomes a tenant-scoped response in Sapienstech.

This is a **conceptual map**, not an inventory. Sapienstech is one Laravel codebase
serving many consultant websites ("tenants"); the request's `Host` header decides
which tenant a response belongs to. The sections below cover resolution, the global
middleware stack, the auth guards, the route groups and their naming convention, and
the per-tenant feature gate.

```text
Host header
   │
   ▼
IdentifyTenant ──► domains table (Domain::normalize → Domain::cacheKey)
   │  binds tenant() + domain() into the container and request attributes
   ▼
web middleware stack  (bootstrap/app.php:27-32)
   EnsureUserDomain → EnsureHierarchyAccess → ApplyPersonalTheme
   ▼
routes/web.php
   public: /  /about  /contact  /blog  /login   │   gated groups:
   consultant/*  ·  studio/*  ·  teacher/*  ·  student/*
   │
   ▼  per-group feature gate
consultant.feature / student.feature / teacher.feature  →  site('features.<key>')
```

The rest of this document explains each stage of that path.

## 1. Tenant resolution

Every request starts in `App\Http\Middleware\IdentifyTenant`. It takes the request's
`Host` header (ports already stripped by `getHost()`), normalises it to the canonical
form used everywhere — lowercase, no trailing dot (`App\Models\Domain::normalize`) —
and looks the host up in the `domains` table through `Domain`, caching the hit for
`config('tenancy.domain_cache_ttl')` seconds. If no tenant is configured for that host
the request is a `404`; a tenant marked suspended is a `403`. Otherwise the resolved
tenant and domain are bound into the container and onto the request, which is what the
`tenant()` and `domain()` helpers and `site()` read for the rest of the request.

Domain identity comes only from the host the request arrived on — never from client
input. The one deliberate exception is `config('tenancy.fallback_domains')`, a
development-only list so `php artisan serve` on localhost works without a hosts-file
entry; it must stay empty in production.

## 2. The web middleware stack

`bootstrap/app.php` appends four middleware to Laravel's `web` group, in this order:

| # | Middleware | What it does |
|---|------------|--------------|
| 1 | `IdentifyTenant` | Resolves the tenant from the host (above). |
| 2 | `EnsureUserDomain` | Logs out a consultant `User` whose `canLoginThrough($domain())` is false and redirects to `/login`. |
| 3 | `EnsureHierarchyAccess` | 404s a teacher reaching `/consultant/*`; restricts settings/blog and `/studio` to the tenant owner for hierarchy tenants. |
| 4 | `ApplyPersonalTheme` | Layers the logged-in user's `preferences.site` on top of `SiteConfig`, guard-agnostic over `web` + `student`. |

The order matters: the tenant must be resolved before anything reads `site()`, and
`ApplyPersonalTheme` runs after auth has started the session.

The same closure also handles redirects: a guest is sent to `student.login` on
`/student/*` and to `login` everywhere else; once authenticated, a user is sent to
`student.dashboard`, `teacher.dashboard` or `consultant.dashboard` depending on the
area.

## 3. Auth guards

`config/auth.php` defines two session guards:

| Guard | Provider | Model | Used by |
|-------|----------|-------|---------|
| `web` | `users` | `App\Models\User` | consultants, tenant admins and teachers |
| `student` | `students` | `App\Models\Student` | the student portal |

There is no separate consultant or teacher guard: consultant access rides the default
`web` guard via the `auth` middleware, and a teacher is a `web` user who additionally
passes the `teacher.role` middleware (`EnsureTeacher`, which 404s anyone whose
`isTeacher()` is false). The student portal is the only area on the dedicated
`student` guard.

## 4. Route-group prefixes and names

`routes/web.php` opens with the public, unauthenticated site, then nests the
authenticated areas under a prefix whose route-name namespace mirrors it:

| Prefix | Name prefix | Middleware / guard | Area |
|--------|-------------|--------------------|------|
| `/`, `/about`, `/contact`, `/blog` | `home`, `about`, `contact`, `blog.` | none (public) | tenant-themed public site + blog |
| `/login`, `/logout` | `login`, `logout` | `guest` / `auth` | shared login page (consultant + student tabs) |
| `/consultant/*` | `consultant.` | `auth` (web guard) | consultant dashboard |
| `/studio/*` | `studio.` | `auth`, `consultant.feature:theme_studio`, `studio.access` | standalone Theme Studio |
| `/teacher/*` | `teacher.` | `auth`, `teacher.role` | teacher panel |
| `/student/*` | `student.` | `guest:student` / `auth:student` | student portal |

The convention is uniform: the URL prefix, the route-name prefix and the area all
match (`consultant/*` ⇄ `consultant.`), so a route's name tells you which guard and
which group it belongs to. The `student.` name prefix is shared by the guest and the
authenticated student groups.

## 5. The feature-flag gate

A tenant can switch product areas off without a deploy. One middleware,
`EnsureConsultantFeature`, is registered under three aliases in `bootstrap/app.php`:

| Alias | Middleware |
|-------|------------|
| `consultant.feature` | `EnsureConsultantFeature` |
| `student.feature` | `EnsureConsultantFeature` |
| `teacher.feature` | `EnsureConsultantFeature` |

A route group applies it with a feature key — for example
`->middleware('consultant.feature:deals')`, `->middleware('consultant.feature:blog_management')`
or `->middleware('student.feature:deals')` — and the middleware 404s the whole group
unless the tenant resolves that key to true via `site("features.{$feature}", false)`.
Because `site()` is tenant-resolved, the same key can be on for one academy and off
for another.

## 6. The other route files

Routing is wired in `bootstrap/app.php` across three files:

- `routes/web.php` — the HTTP routes described above.
- `routes/channels.php` — broadcast authorisation. It defines two **private**
  channels, `chat.conversation.{conversation}` and `chat.actor.{key}`, whose callbacks
  check the session's chat actors against conversation membership, so the websocket
  surface is never wider than the HTTP one.
- `routes/console.php` — scheduled commands: `chat:auto-close` daily at 03:30 and
  `deals:remind-due` daily at 08:00, both `withoutOverlapping()`.

## 7. The middleware

Seven middleware live under `app/Http/Middleware/`:

`IdentifyTenant`, `EnsureUserDomain`, `EnsureHierarchyAccess`, `ApplyPersonalTheme`,
`EnsureConsultantFeature`, `EnsureTeacher`, `EnsureStudioAccess`.
