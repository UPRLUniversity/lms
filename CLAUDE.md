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
