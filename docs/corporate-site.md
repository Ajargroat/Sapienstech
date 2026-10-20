# The corporate site — sapienstech.ir

`sapienstech.ir` is the company site. It is **not a tenant**: it is a static
page plus one PHP endpoint, served by its own nginx `server` block with a
different document root. That separation is the whole design, so read the two
paragraphs below before changing anything.

```text
sapienstech.ir      ──► /etc/nginx/sites-available/sapienstech.ir
                        root /var/www/sapienstech/corporate-site
                        index.html  ·  api.php  ·  fonts/  ·  logo.png

everything else     ──► the Laravel vhost
                        root /var/www/sapienstech/public
                        IdentifyTenant → domains table → tenant
```

**Why not just put the files in `public/`?** The Laravel vhost falls through to
`public/index.php` for every host and resolves it against the `domains` table.
The apex used to belong to the آکادمی جنت tenant (`tenant-one`); now it belongs
to nobody, so the framework would answer 404. The relative URLs inside
`index.html` (`fonts/…`, `api.php`, the logo filename) also only resolve when
`corporate-site/` itself is the web root.

**Exactly one deploy.** The site lives in this repository, so
`git pull` in `/var/www/sapienstech` updates it. No build step, no `npm`, no
migration needed for content changes.

---

## One-time setup

Run these once on the server. `sudo` throughout.

### 0. Confirm what is live now

```bash
sudo nginx -T | grep -n "server_name"
```

If any existing `server_name` line lists `sapienstech.ir` or
`www.sapienstech.ir`, **remove them from that block** before continuing. Two
blocks claiming the same name is not an error nginx rejects — it logs
`conflicting server name` and silently keeps the first one.

### 1. Deploy the files

```bash
cd /var/www/sapienstech
git pull
ls corporate-site/            # index.html api.php config.php fonts/ logo
```

### 2. Database access

`corporate-site/api.php` does not boot Laravel; it opens its own PDO
connection. `config.php` resolves the credentials in this order, and never
stores them itself:

1. `/var/www/sapienstech/corporate-config.php` — optional, gitignored
2. `/var/www/sapienstech/.env` — the app's own `DB_*` values

Both are outside the web root, so neither is reachable over HTTP. In the normal
case you do **nothing**: the lead form writes to the same database as the app.
To use a different database, copy `corporate-config.example.php` to
`corporate-config.php`, set the keys, and `chmod 600` it.

Create the table the form writes to (the migration is idempotent):

```bash
cd /var/www/sapienstech
php artisan migrate --force
```

### 3. Install the vhost

```bash
sudo cp deploy/nginx/sapienstech.ir.conf /etc/nginx/sites-available/sapienstech.ir
sudo ln -s /etc/nginx/sites-available/sapienstech.ir /etc/nginx/sites-enabled/
ls /run/php/                  # if not php8.2-fpm.sock, edit the fastcgi_pass line
sudo nginx -t && sudo systemctl reload nginx
```

### 4. HTTPS

```bash
sudo certbot --nginx -d sapienstech.ir -d www.sapienstech.ir --redirect
```

Certbot rewrites the vhost in place: adds the `443` blocks, points them at the
new certificate, and turns the `:80` block into a redirect. Renewal is already
handled by the certbot timer.

### 5. Force `www` to the apex

Canonical host is the bare domain. Certbot leaves both names on the block it
just created, so first remove `www.sapienstech.ir` from its `server_name`
(lines in both the `:80` and `:443` blocks), leaving `server_name
sapienstech.ir;`. Then add:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name www.sapienstech.ir;

    ssl_certificate     /etc/letsencrypt/live/sapienstech.ir/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sapienstech.ir/privkey.pem;

    return 301 https://sapienstech.ir$request_uri;
}
```

Check the lineage name with `sudo ls /etc/letsencrypt/live/` if it differs.
Then `sudo nginx -t && sudo systemctl reload nginx`.

### 6. Verify

```bash
curl -sI https://sapienstech.ir | head -5                 # 200, text/html
curl -sI https://www.sapienstech.ir | head -5             # 301 → apex
curl -sI https://sapienstech.ir/fonts/Vazirmatn-VariableFont_wght.ttf | head -3
curl -s  https://sapienstech.ir/ | grep -c "ساپینس"       # >0

# lead form end to end
curl -s -X POST https://sapienstech.ir/api.php \
  -H 'Content-Type: application/json' \
  -d '{"name":"تست","phone":"09120000000","source":"modal"}'
# → {"success":true,"id":N,"message":"Lead saved"}
```

Delete the test row afterwards, and confirm the page renders RTL in a browser
with the Vazirmatn font (not a fallback).

---

## Updating the page

Edit `corporate-site/index.html` in the repo, commit, then `git pull` on the
server. Nothing else. If you change the logo or font, keep the relative paths
in `index.html` pointing inside `corporate-site/`.

If you add a new file type, the `location ~* \.(png|svg|ico|woff2?|ttf)$`
block only affects caching; unknown extensions still fall through
`try_files` and are served normally.

## Rollback

```bash
sudo rm /etc/nginx/sites-enabled/sapienstech.ir
sudo systemctl reload nginx
```

The apex then falls back to the Laravel vhost. That only serves the old
آکادمی جنت page if its `domains` row still exists — see below.

## The old tenant (`tenant-one`)

The apex used to resolve to the آکادمی جنت tenant. Once the new vhost is live
that row is dead weight; it is harmless, and it is *not* deleted by this
change. To retire it (back up first), after the new site is confirmed working:

```sql
DELETE FROM domains
WHERE domain IN ('sapienstech.ir', 'www.sapienstech.ir');
```

This removes only the host mapping. The `tenants` row, its students and its
`config/tenants/tenant-one.php` stay untouched, so the tenant can be given a
new host later by inserting a fresh `domains` row.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `{"message":"Database is not configured"}` | `php-fpm` cannot read `/var/www/sapienstech/.env`. Check `sudo -u www-data cat .env`; either fix permissions or copy `corporate-config.example.php` to `corporate-config.php` and fill in the values. |
| `404` on `/`, but the files exist | `root` in the vhost does not point at `corporate-site/`, or `index` is missing. `sudo nginx -T \| grep -A5 sapienstech` to see the effective block. |
| Site still shows the old tenant | A second vhost still claims `sapienstech.ir`. nginx logs `conflicting server name` and keeps the **first** block; remove the name from the old one. |
| `502 Bad Gateway` on `api.php` | Wrong PHP-FPM socket in `fastcgi_pass`. Run `ls /run/php/` and match the version. |
| CSS/font looks like a fallback | The page is being served from a root other than `corporate-site/`, so `fonts/…` 404s. Check the Network tab for the ttf. |
| Browsers still show the old page | Cached DNS/TLS or a CDN in front. `curl -sI` to confirm what the server actually returns before touching config. |

## Security

The previous `config.php` committed a plaintext MySQL password to this
repository, and the repository is public. If that password has not been
rotated yet, do it now and update `.env` — removing the file is not enough,
since Git history and forks keep the old value. Rotating makes the leak inert;
`git filter-repo` is optional afterwards.
