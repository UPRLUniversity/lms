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
