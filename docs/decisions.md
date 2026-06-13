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
