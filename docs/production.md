# Running UPRL LMS in production

Written during the Section 15 hardening sweep. Two of the items below — the queue worker
and the scheduler — are not optional conveniences: **without them the application silently
stops doing half its job**, and nothing on any screen says so.

## 1. Environment

Copy `.env.production.example` to `.env` on the server and fill it in. Do **not** copy
`.env.example`; that is the local template and ships `APP_DEBUG=true`.

```bash
cp .env.production.example .env
php artisan key:generate
```

`APP_KEY` encrypts the payment-gateway credentials stored in the `payment_methods` table.
**Losing it means losing them** — back it up wherever you keep your other secrets, and
never rotate it without first re-entering every gateway credential.

The application refuses to boot with `APP_ENV=production` and `APP_DEBUG=true`
(`AppServiceProvider::guardProductionConfig`). That is intentional: a debug page prints
the environment, database password included.

## 2. Deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run the cache commands on every deploy — a cached config that predates an env change
is a confusing class of bug.

## 3. Queue worker — REQUIRED

Every notification, the certificate PDF render and large report exports are queued. With
no worker running, they queue forever and nobody is told.

`/etc/supervisor/conf.d/uprl-worker.conf`:

```ini
[program:uprl-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/uprl-lms/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/uprl-lms/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread && supervisorctl update && supervisorctl start uprl-worker:*
```

After each deploy: `php artisan queue:restart` — workers hold the old code in memory
until told otherwise.

**Check it is alive:** `php artisan queue:monitor default --max=100`, and watch
`failed_jobs` (`php artisan queue:failed`).

## 4. Scheduler — REQUIRED

One cron entry runs everything scheduled:

```cron
* * * * * cd /var/www/uprl-lms && php artisan schedule:run >> /dev/null 2>&1
```

Without it: digest notifications never send, abandoned guest carts are never swept, and
audit retention (if you enable it) never prunes.

## 5. Storage

- `storage/` and `bootstrap/cache/` must be writable by the web user.
- `php artisan storage:link` must have run, or public media 404s.
- The **private** disk (`storage/app/private`) holds assignment submissions and generated
  certificate PDFs. It must not be served directly by nginx — everything reaches it
  through signed, policy-gated routes. If you add a static-file rule to nginx, exclude it.

## 5a. Upload limits — REQUIRED, and the default will break uploads

The web server and PHP both cap request bodies, and both defaults are smaller than what
this app legitimately accepts. When the web server's cap is the one that bites, the request
never reaches PHP at all: nginx returns its own `413 Request Entity Too Large` page, with no
Laravel styling, no CSRF handling and nothing in `laravel.log`. That page is the signature
of this setting and nothing else.

What the app allows, from `config/media.php`:

| Upload | Largest single file | Files per request | Largest request |
| --- | --- | --- | --- |
| Lesson media (video/audio/slides) | 25 MB | 1 | 25 MB |
| Assignment submission | 20 MB | 5 | 100 MB |
| Lesson and assignment resources | 20 MB | 1 | 20 MB |
| Covers, avatars, branding | 4 MB | 1 | 4 MB |

So the server must accept a 100 MB body, and PHP must accept a 25 MB single file.

nginx, in the `server` block for the site (or in `http` to cover every site):

```nginx
client_max_body_size 128M;
client_body_timeout  300s;   # a 100MB upload over a slow Nigerian link takes minutes
```

`nginx -t && systemctl reload nginx`.

PHP, in the `php.ini` the FPM pool actually loads (`php --ini` under the web user, not the
CLI's, they are usually different files):

```ini
upload_max_filesize = 32M
post_max_size       = 128M    ; must exceed upload_max_filesize, and cover 5 files at once
max_file_uploads    = 20
memory_limit        = 256M    ; must be at least post_max_size
max_execution_time  = 300
```

`systemctl reload php-fpm`.

The two caps fail differently, which is how to tell which one is misconfigured: nginx's
produces the bare `413` page above, while PHP's silently discards the file and Laravel
reports it as an ordinary validation failure on a branded page. If a 413 survives after
raising both, check for a CDN or WAF in front (Cloudflare's free plan caps request bodies
at 100 MB regardless of origin configuration).

TLS terminates at the load balancer, so `TRUSTED_PROXIES` must be set or Laravel generates
`http://` URLs — which breaks payment-gateway callbacks, password-reset links and every
asset. It defaults to `*`, which is correct when the app is only reachable through its own
proxy. Narrow it to the balancer's addresses if the app is ever exposed directly.

## 7. Security headers

`SecurityHeaders` middleware sets `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy` and — over HTTPS only — HSTS.

There is deliberately **no Content-Security-Policy**; see `docs/hardening-report.md` for
why, and for what would need to change first. If your reverse proxy adds one, that is the
better place for it.

## 8. Backups

Back up, at minimum:

- the database (it holds orders, grades, certificates and the audit trail)
- `storage/app/private` (submissions and certificate PDFs — **not** reproducible)
- `APP_KEY`

Public media on Cloudinary is reproducible from source artwork; private files are not.

## 9. Monitoring

Worth an alert:

- queue depth and `failed_jobs` growth
- `storage/logs/laravel.log` at `error` and above
- repeated `login.failed` entries from one address in the audit trail (`/admin/audit`,
  filter Event = "Failed sign-in")
- webhook rejections — a run of 403s from `payments.webhook` means signature failures

## 10. First-run checklist

- [ ] `php artisan migrate --force` applied
- [ ] `php artisan db:seed --class=RolesAndPermissionsSeeder` (roles/permissions only —
      **not** `DatabaseSeeder`, which creates demo accounts with the password `password`)
- [ ] A real super-admin created; every demo account removed
- [ ] Queue worker running, scheduler cron installed
- [ ] Gateway credentials entered under Store → Payment methods (not in `.env`)
- [ ] Branding uploaded under Settings → Branding
- [ ] Support address set under Settings → General
- [ ] Terms and privacy pages replaced with counsel-approved wording
- [ ] `php artisan audit:routes` reports zero unguarded mutating routes
