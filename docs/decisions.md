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

## Section 2 — Courses, Curriculum & Catalogue (2026-06-14)

1. **Four PHP backed enums** model course state: `CourseStatus`
   (draft|review|published|archived, with an `allowedTransitions()` table that is the
   single guard for every status write), `CourseLevel`, `CourseVisibility`
   (public-catalogue|enrolled-only) and `LessonType`. A course reaches the public
   catalogue only when it is BOTH published AND publicly visible (the `inCatalogue`
   scope) — the two are independent so an instructor can keep a published course
   off the public listing.
2. **Status is only ever written by `CoursePublishingService`.** Controllers call
   `submitForReview`/`publish`/`returnToDraft`/`archive`/`restore`; each guards the
   transition table and `publish` re-checks the publish rules (≥1 module, ≥1 lesson,
   summary, cover) so an empty course can never go live even if forced into review.
   The return-to-draft note is required and stored on `courses.review_note`, shown
   in-app on the builder (notifications arrive in Section 8).
3. **The builder persists structure per-action, not via a giant dirty form.** Each
   curriculum edit (add/rename/delete module, add/edit/delete lesson, drag-reorder)
   posts immediately over AJAX and the outline partial is re-fetched and swapped —
   the same server-renders-the-partial pattern the Section-1 data tables use, so it
   degrades gracefully and there is no "unsaved curriculum" to lose. The **settings
   tab** is the one explicit-save surface, with a `beforeunload` dirty-state guard.
   This split is deliberate and consistent within each surface.
4. **Drag-and-drop reorder via SortableJS** (new dep, ~12kb gzipped, lazy-loaded as
   its own chunk only inside the builder). One `reorder` endpoint accepts the whole
   outline (`order => [{module_id, lessons:[…]}]`) and persists module positions,
   lesson positions and cross-module moves in a transaction — and ignores any
   module/lesson id that doesn't belong to the course (a crafted payload can't
   re-home another course's content). `@alpinejs/collapse` was added for the
   catalogue/builder accordions.
5. **Lesson files use a new private `MediaPurpose::LessonMedia`** (PDF/document/
   audio + the exceptional self-hosted video), stored via the Section-0.5
   `PrivateFileService` — never a public CDN URL. The size ceiling is
   `LESSON_MEDIA_MAX_KB` (default 25MB) so the human can raise it for video without
   a code change. Video is **embed-first**: `VideoEmbedService` parses YouTube/Vimeo
   to a privacy-friendly `youtube-nocookie`/`player.vimeo` embed, used for the live
   builder preview, the catalogue free-preview player and the lesson page. A 30MB or
   wrong-type upload is rejected with a clear message (request `max` rule + the
   service's per-purpose mime allow-list). Added `PrivateFileService::delete()` to
   replace/clean up a lesson's file when its type changes.
6. **Policies, not inline checks.** `CoursePolicy` encodes "instructors manage only
   their own, admins manage all, auditors read-only, the publishing decision is
   admin-only"; `FacultyPolicy`/`DepartmentPolicy` are admin-manage / auditor-view.
   `viewAny` excludes students from the management area (they browse the public
   catalogue, which needs no policy). All auto-discovered by Laravel.
7. **The builder is also the admin review screen.** Rather than a separate queue,
   admins open any course's builder and the publish / return-with-note / archive
   panel appears (gated by the `review` ability). The instructor course list shows
   own courses; for admins it shows every course.
8. **Course description is the only `RichHtml` course field**; lesson text content is
   `RichHtml::class` too (sanitized on save, rendered through `<x-ui.prose>`). Summary,
   module/department descriptions and learning objectives are plain escaped text.

## Section 3 — Enrolment, Approvals, Waitlist & Bulk Import (2026-06-15)

1. **One service owns every status write.** `EnrollmentService` is the single place an
   enrollment's status changes (self-enrol, admin-enrol, approve/reject, withdraw,
   promote). Controllers never set status directly. The (user_id, course_id) **unique
   index** makes a duplicate enrolment impossible at the DB level; re-enrolling after
   a withdrawal/rejection updates the same row.
2. **Capacity is "active + pending".** A pending approval request reserves a seat, so
   capacity counts active **and** pending (`EnrollmentStatus::occupiesSeat()`).
   Completed/withdrawn/rejected/waitlisted don't. An open course self-enrols straight
   to active; an approval course to pending (a held seat). Full ⇒ waitlisted.
3. **Queue-safe waitlist promotion.** `syncWaitlist()` runs in a transaction that
   `lockForUpdate()`s the course row, recounts seats, then promotes **at most** the
   number of free seats, earliest-first. The recount-after-lock means two racing
   triggers can never double-promote (covered by a concurrency-safety test). It fires
   on withdrawal, rejection and a raised/cleared capacity. Promotion target follows the
   mode: active (open) or pending (approval).
4. **Waitlist position is derived, never stored** (`Enrollment::waitlistPosition()`),
   so positions renumber for free the moment anyone ahead is promoted or leaves.
   FIFO ordering is `enrolled_at, id` (total + stable under concurrent inserts).
5. **Approvals: admins + the LEAD instructor only.** Co-instructors don't decide
   enrolments. Course-scoped enrolment abilities (`viewRoster`, `manageRoster`,
   `enrollOthers`, `approveEnrollments`) live in `EnrollmentPolicy` but are registered
   as named gates (their subject is a Course, whose policy is CoursePolicy); the
   Enrollment-instance abilities (`approve`/`reject`/`withdraw`) auto-discover.
6. **Bulk CSV is preview-then-confirm.** Upload → `BulkEnrollmentService::analyze()`
   flags each row precisely (unknown email/code, in-file duplicate, already enrolled)
   in two lookups, not one-per-row. The file is staged on the private `local` disk
   under a UUID token; confirm re-reads + re-validates and imports only OK rows.
   Imports **>100 rows** are dispatched to the queued `ProcessEnrollmentImport` job;
   the staged file is its input and is deleted after. Bulk-imported rows carry
   source `bulk` (admin-enrol gained an optional source param).
7. **maatwebsite/excel for the roster CSV export** (`RosterExport`). Local PHP has
   `ext-gd` disabled, which only blocks composer's platform check (the CSV writer
   never touches GD), so `config.platform.ext-gd` is pinned in composer.json to keep
   `composer install` working everywhere; runtime CSV export is unaffected.
8. **The catalogue course page is now enrolment-aware.** A single partial renders the
   right state per viewer/mode/capacity/window: Enrol / Request enrolment / Join the
   waitlist / Awaiting approval / You're enrolled / Enrolment by invitation / opens-or-
   closed. Staff viewing their own course get a "Manage roster" link instead. Added an
   `error` flash channel to the toast stack for graceful self-enrol failures.

## Section 4 — The Learning Player (2026-06-15)

1. **Completion is a state on a unique row, never an increment.** `lesson_progress`
   has a `(user_id, lesson_id)` unique index; "Complete & Continue" `firstOrNew`s that
   row and only writes when not already complete. So a double-click / retried request
   is idempotent by construction — the same single completed row, the same percentage.
   Verified by a double-submit test.
2. **Course percentage is derived, then cached.** The truth is `completed ÷ total`
   lessons (floored, so it only reads 100 when every lesson is genuinely done);
   `LearningService::recalculate()` recomputes it on each completion event and caches it
   on `enrollments.progress_percent` (+ `completed_at`) so list pages never derive it
   per row. At 100% the enrollment flips to `Completed`; un-marking a lesson drops it
   back to `Active`.
3. **One service + one snapshot own the domain.** `LearningService` is the only place
   progress is read/written; it hands the views an immutable `Support\Learning\CourseProgress`
   snapshot (ordered sequence + one progress query) that answers percent, locking,
   neighbours and the resume target — so the sidebar renders with **no N+1**.
4. **Sequential locking is server-side, by index.** A lesson is locked iff its position
   in the flat sequence is *beyond* the first not-yet-completed lesson. `LearnController::show`
   redirects a locked direct-URL hit back to the resume point — the URL is not a
   loophole (covered by UI + direct-URL tests). Stored on `courses.progression_mode`
   (`free|sequential`), editable in the builder's settings.
5. **Resume is one entry point.** `GET /learn/{course}` → `CourseProgress::resumeLesson()`
   (first incomplete, else the start). Every "Continue learning" — My Learning, the
   course page, the dashboard — routes through `learn.resume`, so there's a single
   definition of "where was I?".
6. **Two abilities for the player.** `LessonPolicy@learn` (open a lesson — enrolled
   students, or staff/auditor *previewing*) vs `@track` (write progress — enrolled
   students only). Previews render read-only and leave no progress trail.
   `MediaPolicy@view` was extended so lesson files/resources are downloadable by anyone
   who may `learn` the owning lesson (private media stays signed/policy-gated, never a
   public URL).
7. **Progress writes are async + degrade.** "Complete & Continue" posts JSON
   (`{percent, module_completed, course_completed, next_url, …}`); the button lives in a
   real `<form>`, so with JS off it posts normally and the server redirects to the next
   lesson. Video position is persisted via a lightweight `learn.position` ping (204);
   uploaded-mp4 resume seeks to `last_position_seconds` on load. The module/course
   completion micro-moment is a single tasteful overlay (no confetti spam) that respects
   `prefers-reduced-motion`.
8. **The player has its own focus chrome.** A dedicated `x-learn-layout` (no app sidebar)
   gives the curriculum sidebar + lesson + flow-control footer the whole screen;
   collapsible to a focus mode, a slide-in drawer on mobile, fully keyboard-operable
   (←/→ navigate with focus safety).

## Sections 0–4 — Audit polish & hygiene (2026-06-21)

A full audit of Sections 0–4 (tests green, three static audit passes, live role
walk-through) found no functional gaps. Three cosmetic/hygiene items were resolved so
future sections inherit them:

1. **`--uprl-gold-ink` (#8A6A12) is now a brand token** (`text-gold-ink`). The base
   `--uprl-gold` (#C9A227) fails WCAG AA as text on white, so gold TEXT on light
   surfaces must use the darker ink token. Replaced the one hardcoded hex
   (`badge.blade.php` solid-gold pill). Future gold-on-light text uses `text-gold-ink`.
2. **Skeleton loaders are a shared primitive.** Added `<x-ui.skeleton>` /
   `<x-ui.skeleton-table>` (+ the `.skeleton` shimmer in `app.css`, which freezes to a
   static bar under `prefers-reduced-motion`). The reusable `dataTable` now overlays a
   skeleton on its results region while fetching, so every live list (People,
   Invitations, Roster, and any future one) shows skeletons consistently — satisfying
   the DoD "skeleton loaders for slow lists" rather than only dimming. Showcased on
   `/styleguide`.
3. **Removed dead Breeze leftovers** `components/nav-link` and
   `responsive-nav-link.blade.php` — superseded by the custom sidebar/topbar, rendered
   nowhere, and the only remaining source of non-brand `indigo/gray` classes.
