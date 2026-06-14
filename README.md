# UPRL LMS

The Learning Management System for the **University of Public Relations and
Leadership (UPRL)**, Nigeria. _Creativity, Competence, Character._

Built on Laravel 12, Blade, Alpine.js and Tailwind CSS v3.

---

## Local setup

**Prerequisites:** PHP 8.2+, Composer, Node 18+ & npm, and a MySQL 8 server.

```bash
# 1. Clone and enter the project
git clone <repo-url> uprl-lms
cd uprl-lms

# 2. Install dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate
#   Then edit .env and set your MySQL credentials:
#     DB_DATABASE=uprl_lms
#     DB_USERNAME=<your-mysql-user>
#     DB_PASSWORD=<your-mysql-password>

# 4. Create the database (no mysql CLI needed — uses PHP's PDO)
php -r "require 'vendor/autoload.php'; \$e=(require 'config/database.php')['connections']['mysql']; new PDO('mysql:host='.\$e['host'].';port='.\$e['port'], \$e['username'], \$e['password']) and (new PDO('mysql:host='.\$e['host'].';port='.\$e['port'], \$e['username'], \$e['password']))->exec('CREATE DATABASE IF NOT EXISTS '.\$e['database'].' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
#   (Or just create a database named `uprl_lms` in phpMyAdmin / your GUI.)

# 5. Migrate and seed demo data
php artisan migrate --seed

# 6. Build front-end assets
npm run build

# 7. Serve
php artisan serve
```

Open <http://127.0.0.1:8000>.

### Demo credentials

| Email             | Password   |
| ----------------- | ---------- |
| `admin@uprl.test` | `password` |

After signing in you land on the styled dashboard at `/dashboard`.

---

## Development workflow

- `npm run dev` — Vite dev server with hot reload (run alongside `php artisan serve`).
- `php artisan test` — run the test suite (PHPUnit, in-memory SQLite).
- `php artisan migrate:fresh --seed` — reset the database to a clean demo state.
- **`/styleguide`** — the living design reference (every component and brand
  token). Available in local/testing environments only.

## Brand & design system

- Brand colour tokens live in **one place**: the `:root` block of
  [`resources/css/app.css`](resources/css/app.css), surfaced to Tailwind in
  [`tailwind.config.js`](tailwind.config.js) (`crimson`, `ink`, `surface`,
  `card`, `success`, `gold`, `line`). Never hard-code hex in views.
- Logo files go in [`public/images/brand/`](public/images/brand/) — see the
  README there. The app falls back to an inline monogram until real artwork is
  supplied, with no code change needed when you add the files.
- Reusable UI lives under `<x-ui.*>` (button, card, badge, input, field, modal,
  empty-state, stat, icon). Browse them all at `/styleguide`.

## Shared foundations (files & rich text)

Two canonical primitives every feature reuses (see [`CLAUDE.md`](CLAUDE.md)):

- **File storage** — `MediaUploadService` for public images, `PrivateFileService`
  for sensitive files. Purpose → disk/visibility/mime/size is configured once in
  [`config/media.php`](config/media.php). Run `php artisan storage:link` once so
  public files are web-served.
- **Rich text** — `<x-ui.rich-editor>` (self-hosted TinyMCE, no cloud key) +
  the `RichHtml` cast (sanitizes on save via [`config/purifier.php`](config/purifier.php))
  + `<x-ui.prose>` for output. Try both editor profiles at `/styleguide`.

### Env keys

```dotenv
# Backend for PUBLIC images: "local" (default; dev/test, no account) or "cloudinary"
MEDIA_DRIVER=local
# Only needed when MEDIA_DRIVER=cloudinary (from the Cloudinary dashboard)
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
```

Private files always use the local `private` disk (S3-compatible later via config).

## Project docs

- [`docs/audit.md`](docs/audit.md) — audit of the starting template.
- [`docs/decisions.md`](docs/decisions.md) — running log of engineering decisions.
- [`CLAUDE.md`](CLAUDE.md) — the project constitution (conventions, brand, DoD).
