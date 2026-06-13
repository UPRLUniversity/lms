# UPRL LMS — Template Audit (Section 0)

Audited 2026-06-13 against commit `42b1d4d` ("first commit"). This is the baseline
the project builds on.

## Framework & language

| Item | Finding |
| --- | --- |
| Laravel | `laravel/framework ^12.0` (Laravel 12) |
| PHP requirement | `^8.2` (local: PHP 8.2.12 via XAMPP) |
| Bootstrap style | Modern Laravel 11+ `bootstrap/app.php` (`Application::configure`), no custom middleware or providers registered |

## Frontend stack

| Item | Finding |
| --- | --- |
| Templating | Blade (no Livewire, no Inertia, no Vue/React) |
| CSS | **Tailwind CSS v3** (`tailwindcss ^3.1.0`, `tailwind.config.js`, PostCSS pipeline, `@tailwind` directives in `resources/css/app.css`) |
| JS | Alpine.js 3, Axios |
| Build | Vite 7 + `laravel-vite-plugin` 2 |
| Fonts | Figtree via Bunny Fonts (template default — replaced by brand typography in Section 0) |

## Auth scaffolding

**Laravel Breeze v2.4, Blade stack** — complete and functional:

- Controllers in `app/Http/Controllers/Auth/` (login, register, password reset,
  email verification, password confirm/update) + `ProfileController`.
- `routes/auth.php` with the full guest/auth route set; `routes/web.php` has
  `/` (welcome), `/dashboard` (auth+verified), `/profile` (edit/update/destroy).
- Views: `resources/views/auth/*` (6 views), `profile/*`, `dashboard.blade.php`,
  layouts (`app`, `guest`, `navigation`) and 13 Breeze Blade components
  (buttons, text-input, labels, dropdown, modal, nav links).
- `App\View\Components\AppLayout` / `GuestLayout` layout classes.

## Database & migrations

Three stock migrations (users + password_reset_tokens + sessions; cache +
cache_locks; jobs + job_batches + failed_jobs). **Queue, cache and session
tables already exist** — no extra migration needed for `QUEUE_CONNECTION=database`.

Models: `User` only (HasFactory, Notifiable, hashed password cast).
Seeders: `DatabaseSeeder` creating one test user. Factories: `UserFactory`.

## Environment defaults (`.env.example`)

Already aligned with the project plan: `DB_CONNECTION=mysql`,
`DB_DATABASE=uprl_lms`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`,
`CACHE_STORE=database`, `MAIL_MAILER=log`, `FILESYSTEM_DISK=local`.

## Testing

- **PHPUnit 11** (no Pest). Suites: `tests/Unit`, `tests/Feature`.
- Full Breeze auth Feature suite (authentication, registration, password
  reset/update/confirm, email verification, profile) + example tests.
- `phpunit.xml` runs tests on **in-memory SQLite** with array cache/session and
  sync queue.

## Broken / obsolete findings

1. **Stray dependency:** `package.json` lists `@tailwindcss/vite ^4.0.0`
   (a Tailwind **v4** plugin) while the project is configured for Tailwind v3
   via PostCSS; `vite.config.js` never imports it. Unused → removed in Section 0.
2. `welcome.blade.php` is the stock Laravel landing page with a large embedded
   CSS fallback — left functional for now (route must keep returning 200 for
   `ExampleTest`), to be replaced when a public landing page is in scope.
3. Nothing else broken: configs are stock Laravel 12 defaults, no leftover
   third-party template branding.

## Installation state at audit time

`vendor/`, `node_modules/`, `.env`, and the `uprl_lms` database did not exist —
fresh clone. Local toolchain: PHP 8.2.12, Composer 2.9.5, Node 22 / npm 10,
MySQL 8.0 running as Windows service `MySQL80` (no `mysql` CLI on PATH).
