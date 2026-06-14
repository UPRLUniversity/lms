# UPRL LMS — Decision Log

Decisions made where CLAUDE.md allowed discretion. Newest at the bottom.

## Section 0 — Foundation, Brand & App Shell (2026-06-13)

1. **Stay on Tailwind v3.** The template is wired for v3 (config file, PostCSS,
   `@tailwind` directives). Upgrading to v4 would churn working scaffolding for
   no Section-0 benefit ("detect, don't destroy"). The stray, unused
   `@tailwindcss/vite` v4 package was removed from `package.json`.
2. **Brand tokens live in `resources/css/app.css` `:root` as space-separated RGB
   channel triplets** (hex kept in comments). `tailwind.config.js` maps them via
   `rgb(var(--uprl-*) / <alpha-value>)` so Tailwind opacity modifiers
   (`bg-success/10`) work. One source of truth; no hex in config or views.
3. **Fraunces** chosen as the display serif (over Playfair Display) — warmer and
   more distinctive; loaded with Inter via Bunny Fonts.
4. **Light theme only.** `dark:` variants stripped from all touched views. The
   brand defines a light palette; half-styled OS dark mode would look broken.
   A deliberate dark theme can be designed later if requested.
5. **Breeze `x-modal` kept untouched** (profile pages depend on it). New code
   uses `<x-ui.modal>`, which reuses the same Alpine `open-modal`/`close-modal`
   window-event API; profile pages can migrate in a later section.
6. **`layouts/navigation.blade.php` deleted** (human approved). The app shell's
   sidebar + topbar replace it; the logout form moved to the topbar user menu.
7. **`/styleguide` is gated at route registration** (`local`/`testing` envs
   only). In production the route simply doesn't exist (404), including under
   `route:cache`. Intentional — do not "fix".
8. **Sidebar nav is config-driven** (`config/navigation.php`) with a `roles`
   key per item. Until spatie/laravel-permission arrives (later section), items
   with `roles => ['*']` show for everyone and `route => null` entries render
   as disabled placeholders. The Blade markup won't need to change when real
   role checks land.
9. **Logo files referenced via `config/brand.php`** + `<x-brand.logo>` with a
   file-exists check and an inline SVG fallback, so dropping real logo files
   into `public/images/brand/` requires zero code changes.

## Section 0.5 — Shared Foundations (Storage + Rich Text) (2026-06-13)

1. **Cloudinary via the official `cloudinary/cloudinary_php` SDK directly**, not
   the `cloudinary-labs/cloudinary-laravel` wrapper. We already wrap every upload
   behind our own `MediaUploadService` interface, so the wrapper's facade/provider
   add no value and one more dependency to track; the first-party SDK (v3.1) is
   actively maintained and keeps the test/local path (Local driver) free of any
   Cloudinary service-provider boot.
2. **TinyMCE self-hosted OSS (GPLv2+) build via npm + Vite** (v8.6), no cloud API
   key. Required `license_key: 'gpl'` for v7+. Bundled deterministically with
   Vite `?inline` skin/content CSS (no HTTP skin fetch), lazy-loaded as its own
   chunk only on pages that contain `[data-rich-editor]`.
3. **Sanitizer: `mews/purifier`** (v3.4, wraps ezyang/htmlpurifier). Profiles
   `rich`/`basic` in `config/purifier.php` mirror the editor's `valid_elements`.
   Applied centrally via the `RichHtml` Eloquent cast (sanitize on `set`).
4. **Image dimensions via native `getimagesize()`**, not intervention/image:
   v4 requires PHP 8.3 (we're on 8.2) and v3 is EOL. The Local driver records
   width/height only; Cloudinary handles responsive derivatives in production.
   No server-side resizing now.
5. **Private temporary URLs implemented with signed routes** (`URL::temporarySignedRoute`)
   rather than a disk driver's `temporaryUrl`, so they work locally and under
   `Storage::fake` without an S3-compatible backend, and never expose a public URL.
6. **Test images use a committed PNG fixture** (`tests/Fixtures/pixel.png`) instead
   of `UploadedFile::fake()->image()`, so the suite needs no GD extension (the app
   reads dimensions with `getimagesize()`, which is GD-independent).

## Section 1 — Identity & Access (2026-06-14)

1. **`spatie/laravel-permission` v6** for roles/permissions. Five roles are a fixed
   string-backed `App\Enums\Role`; granular permissions are `App\Enums\Permission`.
   The matrix lives in one idempotent `RolesAndPermissionsSeeder`. The auditor is
   read-only *by construction* — it receives only the `*.view` permission subset.
2. **Super-admin via `Gate::before`**, not a wildcard permission — it short-circuits
   every policy/ability check. The privilege-escalation rule ("only a super-admin
   may grant/invite admin or super-admin") is a single `grantRole` ability
   (`Gate::define('grantRole', [UserPolicy::class, 'grantRole'])`) reused by the
   store/update/invite FormRequests.
3. **Adapted Breeze, did not fork it.** `User` now implements `MustVerifyEmail`
   (verification was previously inert); registration assigns the `student` role;
   the deactivation gate + login auditing live in the existing `LoginRequest`.
4. **Deactivation, never deletion.** An `is_active` flag gates login (in
   `LoginRequest`, only after valid credentials so it doesn't leak which emails
   exist) and a global `EnsureUserIsActive` web middleware ends a live session the
   moment an admin flips the flag. `UserPolicy` forbids self-deactivation and an
   admin deactivating a super-admin.
5. **`email_verified_at` kept guarded** (not added to `$fillable`). Admin-created
   users, accepted invitations and seeded demo accounts are marked verified through
   `markEmailAsVerified()` rather than mass assignment — mass-assigning it silently
   no-ops and would have left every seeded account stuck behind the verify gate.
6. **Invitations store only a SHA-256 hash of the token**; the raw token exists
   only inside the e-mailed `temporarySignedRoute` link (signed + 7-day expiry +
   single-use). Acceptance is constant-time (`hash_equals`) and transactional.
   `UserInvitation` is itself `Notifiable` so the queued mail routes to its email.
7. **Avatars reuse the Section-0.5 `MediaUploadService`** (purpose `Avatars`,
   configured to 256×256) — no new storage path. Replacing an avatar destroys the
   previous Media (file + row) first, so a user keeps exactly one. The 256px resize
   is a Cloudinary transformation in production; the local driver stores as-is.
8. **`bio` is a plain `text` column with a textarea**, not a TinyMCE/`RichHtml`
   field. A short self-description isn't worth the rich-editor surface (or its XSS
   risk); it's escaped on output like any plain string.

## Section 1 — Feedback follow-ups (2026-06-14)

1. **Queued mail kept; local dev defaults to `sync`.** Invitations are queued
   (`ShouldQueue`), so without a worker they sat in the `jobs` table undelivered —
   verification mail arrived only because the framework sends it synchronously. The
   architecture (queued database driver) is unchanged for production; the local
   `.env` now ships `QUEUE_CONNECTION=sync` so queued mail sends inline with no
   worker. `.env.example` stays `database` with a comment pointing at `queue:work`.
2. **Branded transactional e-mail via a published markdown theme** (`uprl.css`)
   rather than bespoke Mailables — verification, password reset and invitations all
   inherit it. Crimson header band, serif headings (Georgia/Times fallback, since
   e-mail clients ignore web fonts), gold motto in the footer. Voice for the
   framework's own mails is set with `VerifyEmail/ResetPassword::toMailUsing()`.
   Local `/mail-preview/{type}` route renders them for eyeballing.
3. **Live admin tables via a reusable Alpine `dataTable` + server partials**, not a
   third-party datatable lib and not Livewire/Inertia. The index action returns the
   `_table` partial for AJAX (`X-Requested-With`/`wantsJson`) and the full page
   otherwise; search/role/sort/pagination and row actions fetch-and-swap with no
   reload, syncing the URL via `history.replaceState`. Sort/pagination stay real
   `<a data-nav href>` links and actions stay `<form data-ajax>`, so the table is
   fully functional without JavaScript (progressive enhancement). Sortable columns
   are whitelisted server-side (`name`/`status`/`last_login`) — an unknown `sort`
   falls back to `name`, so the query is injection-safe.
4. **Single feedback + confirmation system.** All action feedback now flows through
   one global, self-dismissing **top-right toast** stack (`<x-ui.toasts>`, Alpine):
   it renders `session('status')` flashes on load and listens for a `toast` window
   event, so server redirects and AJAX actions look identical. Inline flash banners
   and the old bottom-right toast were removed. All confirmation/destructive dialogs
   use **branded SweetAlert2** (`resources/js/confirm.js` → `window.uprlConfirm`,
   crimson buttons, serif title) instead of native `confirm()`. These are the
   user's standing UI preferences (saved to assistant memory for consistency).
5. **Custom branded landing page** replaces the Laravel starter `welcome` view —
   crimson hero with the rotating sunburst motif, values (Creativity/Competence/
   Character), feature highlights and a CTA, all auth-aware (guests see register/
   login; signed-in users see "Continue learning"). Login now links to register.
