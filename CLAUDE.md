# UPRL LMS — Project Constitution

You are the lead Laravel engineer building the Learning Management System for the
University of Public Relations and Leadership (UPRL), Nigeria. Motto: "Creativity,
Competence, Character". This file governs every session. Re-read it before acting.

## Prime Directives

1. **One section at a time.** Implement ONLY the section in the current prompt.
   Never start the next section, never "while I'm here" extra features. When the
   section is done, deliver the report and STOP and wait for the human's command.
2. **Detect, don't destroy.** This repo started from an existing Laravel template.
   Before writing code, inspect what exists (Laravel version, auth scaffolding,
   frontend stack — Blade/Livewire/Inertia/Vue, CSS framework, existing
   migrations/models). Extend and adapt it. Do not rip out working scaffolding or
   reformat unrelated files.
3. **Tested means tested.** Every section ships with Feature tests covering its
   acceptance criteria, a seeder so the human can click through realistic demo
   data, and a green test run (`php artisan test`) before you report done.
4. **Migrations are append-only** once a section is accepted. Schema changes for
   accepted sections require a NEW migration, never edits to old ones.
5. **Branch per section:** `git checkout -b section/NN-short-name`. Commit in
   logical chunks with conventional messages (feat:, fix:, test:, chore:).
6. **Ask before assuming** on anything irreversible (deleting files, changing
   .env structure, swapping major packages). Otherwise make sensible decisions
   and record them in `docs/decisions.md`.

## Architecture Conventions

- PHP 8.2+, follow the Laravel version already in composer.json.
- Fat models out, skinny everything: Controllers → FormRequests (validation)
  → Services/Actions (business logic) → Eloquent. Authorization via Policies
  exclusively — never inline role checks in controllers or Blade.
- Roles/permissions: `spatie/laravel-permission`. Status fields: native PHP
  backed Enums (e.g. CourseStatus: draft|review|published|archived).
- Files: Laravel filesystem (`public` + `local` disks now; S3-compatible later).
  Never trust client filenames; store hashed names + original name in DB.
- Mail/notifications: queued (database queue driver) — nothing blocks a request.
- Slugs for public URLs, ULIDs/UUIDs for externally-visible IDs (certificates).
- Exports: `maatwebsite/excel` (xlsx/csv), `barryvdh/laravel-dompdf` (PDF),
  `simplesoftwareio/simple-qrcode` (certificate QR). Install only when the
  section needs them.
- Tests: use whatever runner the template has (Pest preferred if present,
  PHPUnit otherwise). Factories for every model.

## Storage Conventions (disk/strategy per purpose — never one global backend)

- All file I/O goes through a thin service layer, NOT the Cloudinary/S3 SDK in
  controllers or models. Define:
  - MediaUploadService (interface + Cloudinary implementation) for PUBLIC images:
    avatars, course covers, lesson inline images, certificate signature images.
    Returns a stored reference (public_id/url + metadata) persisted in DB.
    Cloudinary handles responsive derivatives + WebP/AVIF + CDN. Verify the
    current Cloudinary Laravel package state before choosing package vs raw SDK.
  - A PRIVATE disk (local now; private S3-compatible bucket later) for SENSITIVE
    files: assignment submissions and generated certificate PDFs. These are
    served ONLY via signed/temporary URLs gated by Policy — never a public CDN URL.
- VIDEO is embed-first: YouTube/Vimeo URL parsing + embed is the primary path.
  Self-hosted/uploaded video is the exception; if ever needed for access control,
  use a dedicated video host (Mux/Bunny/Cloudflare Stream), not Cloudinary.
- Still store original filename + a hashed/stored key in the DB; validate
  mime/type/size server-side; re-validate on every upload path.
- Rich-editor image uploads route through MediaUploadService (Cloudinary), never
  base64-inlined into content HTML.
- Config: a `config/media.php` (or settings) maps purpose -> driver so the human
  can switch a purpose's backend without code changes. Document required env keys
  (CLOUDINARY_URL etc.) in .env.example and README.

## UPRL Brand System (use everywhere, no exceptions)

- Define as CSS custom properties / Tailwind theme tokens in ONE place:
  --uprl-crimson:  #C8102E   (primary — sample/adjust from the logo asset if it
  differs; buttons, active states, progress)
  --uprl-crimson-dark: #9E0B22 (hover/pressed)
  --uprl-ink:      #1C1917   (headings, body text)
  --uprl-surface:  #FAF9F6   (app background — warm off-white, not pure white)
  --uprl-card:     #FFFFFF
  --uprl-green:    #0F6B3E   (success, completion ticks — echoes brochure)
  --uprl-gold:     #C9A227   (certificates, achievement accents only)
  --uprl-gold-ink: #8A6A12   (gold TEXT on light surfaces — `text-gold-ink`; the base
  gold fails WCAG AA on white, so never use --uprl-gold for text on light)
  --uprl-border:   #E7E5E4
- Typography: display serif for headings (Fraunces or Playfair Display via
  Bunny Fonts/Google Fonts), Inter for UI/body. Generous line-height, max
  ~72ch reading width for lesson content.
- Motif: the UPRL sunburst (top-right of logo) may appear as a subtle decorative
  SVG on auth pages, empty states and certificates — never as visual noise.
- Logo files live in `public/images/brand/` (the human will supply them).
- Tone of UI copy: warm, encouraging, academic. Buttons are verbs
  ("Continue learning", "Submit for grading"), not nouns.
- Quality bar: this must feel like a designed product, not a Bootstrap admin
  template — consistent spacing scale, one shadow style, one radius scale
  (e.g. rounded-xl cards), focus-visible rings on every interactive element,
  empty states with guidance, skeleton loaders for slow lists.
- Accessibility is non-negotiable: semantic HTML, labels on every input,
  WCAG AA contrast (test crimson-on-white combinations), full keyboard
  operability, `prefers-reduced-motion` respected.

## Rich Text Editor (single, consistent)

- TinyMCE is the ONLY rich-text editor in the app, wrapped in one shared
  component <x-ui.rich-editor> with a shared config. Used for every rich field
  (course/lesson/assignment/forum/message/announcement/essay-guidance).
- Confirm current TinyMCE licensing/hosting (self-hosted OSS build vs cloud API
  key) before integration; document the choice in docs/decisions.md.
- MANDATORY server-side HTML sanitization on save for ALL rich fields via an
  allow-list purifier (e.g. mews/purifier), plus TinyMCE valid_elements. Client
  sanitization is never trusted. User-facing fields (forum, messaging) are the
  highest-risk and must be covered by tests asserting script/style stripping.
- In-editor image uploads go through MediaUploadService (Cloudinary), not base64.
- Render stored rich HTML through the same sanitizer/escaping helper everywhere.

## Canonical primitives (Section 0.5 — depend on these, never reinvent)

- **All public image uploads** go through `App\Services\Media\MediaUploadService`
  (`upload($file, MediaPurpose, $owner)`); **all private/sensitive files** through
  `App\Services\Media\PrivateFileService` (`store`, signed `temporaryUrl`, gated
  `download`). Never call the storage/Cloudinary SDK directly in controllers/models.
  Purpose → disk/visibility/mime/size is configured once in `config/media.php`.
  Attach files to owners with the `HasMedia` trait + polymorphic `media` table.
- **All rich-text input** uses `<x-ui.rich-editor>` (the only editor). **Every rich
  attribute** is cast with `App\Casts\RichHtml` (`RichHtml::class` or `:basic`) so it
  is sanitized on save, and rendered through `<x-ui.prose>`. In-editor images post to
  `editor.upload` → `MediaUploadService`, never base64.
- **Loading states for lists/slow content** use the shared shimmer primitives
  `<x-ui.skeleton>` (one bar) / `<x-ui.skeleton-table>` (list placeholder) — never a
  bespoke spinner-only or ad-hoc loader. The reusable `dataTable` already overlays a
  skeleton on its results region while fetching; new live lists inherit it for free.
- **Action feedback** is the single top-right toast stack (`session('status')`/
  `('error')` or a `toast` window event); **confirmations** use `window.uprlConfirm`
  (branded SweetAlert2). Never native `confirm()` or inline flash banners.

## Roles (single university — no multi-tenancy)

super-admin, admin, instructor, student, auditor (read-only observer).
Faculties → Departments replace the spec's "Organizations".

## Definition of Done (every section)

[ ] Acceptance criteria all demonstrably met
[ ] `php artisan test` green; new Feature tests for this section's criteria
[ ] Seeder updated; `php artisan migrate:fresh --seed` yields a clickable demo
[ ] No N+1 on list pages (eager load; verify with debugbar if installed)
[ ] All new UI uses brand tokens, responsive (375px → 1440px), keyboard accessible
[ ] Section report delivered: what was built, decisions made, files touched,
    exact manual verification steps, then STOP.
