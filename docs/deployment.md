# Deployment — the Ubuntu server

How to take a change from this repository to the live host. This is a
**conceptual runbook**: one flow, then one small table per moving part. Commands
are the real ones this project uses (`composer.json`, `README.md`); the local
setup is in `README.md` and is not repeated here.

```text
local repo ──git push──► origin ──git pull──► /var/www/sapienstech
                                                   │
                     composer install --no-dev ────┤
                     npm ci && npm run build ───────┤
                     php artisan migrate --force ───┤
                     php artisan config:cache ──────┤   (route:cache, view:cache)
                     php artisan queue:restart ─────┤
                     php artisan reverb:start ──────┘   (under supervisor)
                                                   │
                                  nginx vhost ──► browser
```

The demo pages (`public/demo/`) are **static files** — they need steps 1–3 and
the vhost only. Everything else is the shared app surface.

## 1. Pull the change

| step | command | why |
|---|---|---|
| fetch | `cd /var/www/sapienstech && git pull` | the working copy is the deploy target |
| check | `git log --oneline -1` | confirm the expected commit landed |

## 2. PHP dependencies

| step | command | why |
|---|---|---|
| install | `composer install --no-dev --optimize-autoloader` | production classmap, no dev packages |

## 3. Front-end build

| step | command | why |
|---|---|---|
| install | `npm ci` | lockfile-exact, not `npm install` |
| build | `npm run build` | emits `public/build` (Vite manifest the Blade `@vite` needs) |

`public/build` is generated, never committed — a deploy that skips this step
404s every CSS/JS asset.

## 4. Database

| step | command | why |
|---|---|---|
| migrate | `php artisan migrate --force` | `--force` because the host runs in production |

## 5. Caches

| step | command | why |
|---|---|---|
| config | `php artisan config:cache` | `.env` is read once; `env()` returns null afterwards |
| routes | `php artisan route:cache` | |
| views | `php artisan view:cache` | |
| restart queue | `php artisan queue:restart` | workers must reload the new code |

Because `config:cache` freezes `.env`, any `.env` change requires re-running it.
`config/tenancy.php` resolves its `env()` values at load time for exactly this
reason.

## 6. The domain

| item | value |
|---|---|
| document root | `/var/www/sapienstech/public` |
| front controller | `public/index.php` |
| try_files | `try_files $uri $uri/ /index.php?$query_string;` |
| PHP | `fastcgi_pass` to the php-fpm socket |

The document root **must** be the repo's `public/`, not the repo root — that is
what makes `public/demo/theme.css`'s root-absolute `/fonts/…` URL resolve, and
what makes the static demo files win over the framework.

`try_files` order matters: an existing file (`public/demo/index.html`) is served
by nginx directly and never reaches Laravel; everything else falls through to
`index.php`, where `IdentifyTenant` resolves the host.

### The corporate site (sapienstech.ir)

`sapienstech.ir` is the one host that does **not** use this document root. It
has its own vhost — checked in as `deploy/nginx/sapienstech.ir.conf`, installed
as `/etc/nginx/sites-available/sapienstech` (the pre-existing vhost file; do not
add a second file for the same name) — pointing at
`/var/www/sapienstech/corporate-site`. The apex is the company site, not a
tenant, so it must not fall through to `index.php`.

That host is still `200` when it is broken. If its server block points at the
Laravel document root, nginx answers every request from `public/index.php`, and
`IdentifyTenant` then 404s the paths the framework does not own — the visitor
sees the Laravel error page on a site that looks up. Two quick probes tell the
two roots apart, because `fonts/` and `api.php` exist only under
`corporate-site/`:

| probe | corporate root | Laravel root |
|---|---|---|
| `curl -sI https://sapienstech.ir/api.php` | `405` (or `204`) + `Access-Control-Allow-*` | `404`, HTML body |
| `curl -s https://sapienstech.ir/fonts/` | font file or `403` | `404` |

It is still deployed by `git pull` in this repository; a content change needs
no `npm`, no `migrate` and no cache rebuild. Full runbook, including the
`leads` table and the HTTPS setup: `docs/corporate-site.md`.

## 7. HTTPS

| step | command |
|---|---|
| certificate | `certbot --nginx -d drkafimentoring.ir` |
| renew | handled by the certbot timer; no cron entry needed |

## 8. Reverb and the queue

Both run as long-lived processes under `supervisor`.

| process | command | notes |
|---|---|---|
| Reverb | `php artisan reverb:start` | WebSocket server; bind address is `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` |
| queue | `php artisan queue:work` | `QUEUE_CONNECTION=database`; `queue:listen` in dev |

The **client** side is a separate set of values: `REVERB_HOST`, `REVERB_PORT`
and `REVERB_SCHEME` are the *public* ones the browser connects to, and
`app/Support/ChatJs.php` feeds them straight into pusher-js. If they do not match
what nginx/Reverb expose, chat fails silently. The WebSocket path is
`/app/{appKey}` — proxy it through the vhost like any other upgrade route.
`REVERB_SERVER_HOST=0.0.0.0` (bind) and `REVERB_HOST` (public) are different
settings on purpose.

## 9. The demo pages (static)

`public/demo/index.html`, `sign-in.html`, `theme.css` and `demo.css` are
ordinary static files. nginx serves them directly, so **no tenant row, no
`config/tenants/` file, no `TENANT_FALLBACK_DOMAINS` entry is involved** —
`IdentifyTenant` never runs for a file nginx serves. `/demo/` works the moment
the files are pulled.

Two production invariants from `.env.example` and `config/tenancy.php`:

- **`TENANT_FALLBACK_DOMAINS` must be empty.** Any host listed there is served
  whichever tenant owns the primary domain.
- **Never set `SESSION_DOMAIN`.** Sessions are deliberately host-only; a shared
  parent domain breaks tenant isolation.

## 10. Verify in the browser

| check | expected |
|---|---|
| `/demo/index.html` | renders with the demo palette (light, slate-teal) |
| `/demo/sign-in.html` | one centred card, no role tabs |
| scheme toggle | **absent** (the demo declares a single scheme) |
| `drkafimentoring.ir/` | the tenant once created — a `404` until then (expected, not a failure) |
| `php artisan about` | shows the cached config |
| WS endpoint | answers on `/app/{appKey}` |

If the demo renders washed out or with invisible borders, the browser is older
than 2023 — the tokens use `color-mix(in oklab, …)` (Chrome 111+, Safari 16.2+,
Firefox 113+).

## 11. Later — make it a real tenant

When the demo is approved, scaffold the tenant (this is what creates the config
file the demo's tokens are meant to move into):

```bash
php artisan tenant:make "علیرضا کافی" drkafimentoring \
    --domain=drkafimentoring.ir --archetype=<one of config/archetypes/*.php> \
    --admin-email=you@example.com
```

That command writes the tenant config file, the `domains` row and the asset
folder, after which `drkafimentoring.ir/` serves the real landing page. The demo
stays reachable at `/demo/` for reference.

Once the tenant exists, populate it with its real content (owner, students and the
content every dashboard surface reads). The seeder is idempotent — rerunning it is a
no-op, and it never overwrites an existing password:

```bash
php artisan tenant:seed drkafimentoring
```
