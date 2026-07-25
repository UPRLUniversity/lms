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

## Section 5 — Assessments, question bank & taking engine (2026-06-21)

1. **Matching is graded proportionally.** `points × (correctPairs / totalPairs)`, rounded
   to 2 dp (`MatchingGrader`). More forgiving than all-or-nothing and rewards partial
   knowledge — the common LMS behaviour.
2. **Multi-select MCQ is all-or-nothing.** Zero credit if any wrong option is chosen or any
   correct option missed (`McqMultiGrader`), per the section brief. True/false and
   single-answer MCQ share `McqSingleGrader`.
3. **Fill-blank** is a single blank (MVP); the response is trimmed and matched against the
   accepted list, folding case when the question's `case_insensitive` flag is set (default
   on). Documented so multi-blank can be added later without breaking the cast.
4. **Scenario** is a container of nested sub-questions. Each sub is graded by its own type;
   scenario points = Σ sub points. If any sub is an essay the whole scenario routes to
   manual grading, with the objective sub-score shown to the grader as a hint
   (`GradingService::scenarioObjectiveSubtotal`). Scenario sub-prompts are rich HTML inside
   the parent's `payload` JSON, so they bypass the `RichHtml` cast — `QuestionBankService`
   sanitises them through mews/purifier (`rich` profile) on save.
5. **The timer is server-authoritative.** `attempts.expires_at` (started_at + limit) is the
   single source of truth; the client countdown re-anchors to it on every load, so a
   refresh can't extend it and a walk-away auto-submits at zero (`AttemptService::ensureFresh`
   on load + the client timer). The whole attempt presentation (question order, shuffled
   options, pooled draw, tokenised matching rights) is frozen into `attempts.layout` at
   start, so a refresh never reshuffles and every saved answer is validated against it.
6. **No correct answers reach the client before submission.** The take payload
   (`AttemptPresenter::takeItems`) strips `is_correct`/accepted answers and tokenises
   matching rights (opaque `token` → `pair_id` held server-side in the layout), so the
   correct mapping can't be read off the DOM. Correct answers/explanations appear only via
   `reviewItems`, gated by `ReviewPolicy` (immediately / after_close / never) and only for a
   graded attempt.
7. **Authoring is gated by ownership, not a flat permission.** Question/Assessment policies
   defer to `CoursePolicy::update` (the course's instructors + admins), mirroring
   curriculum management. The pre-defined `assessments.view` / `assessments.grade`
   permissions gate the grading queue and the read-only auditor. Manual grade entry is
   rubric-free points + feedback for now; **rubrics arrive in Section 6** and will slot into
   the same `attempt_answers` rows.
8. **Progress integration is additive.** A new `CurriculumOutline` (lessons + published
   assessments interleaved by placement) drives the sidebar and the sequential lock
   frontier; `CourseProgress` now counts *required* assessments toward the course percentage
   alongside lessons (lesson-only callers are unaffected — the new counts default to zero).
   Standalone assessments sit at the end of the outline.

### Section 5 — review addendum (2026-06-22)

A full browser QA pass after delivery resolved one spec gap and recorded two accepted
enhancements:

9. **Builder curriculum now interleaves assessments inline.** Pre-module assessments
   render before a module's lessons and post-module after (gold-tinted, non-draggable
   chips with the clipboard icon), matching the player sidebar and the Section-2 brief
   ("pre sits before its module's lessons, post after"). Standalone assessments keep
   their own section below the outline. The lesson drag-reorder list is untouched (chips
   sit outside `[data-lesson-list]`), so SortableJS still only reorders lessons.
10. **Accepted as-is (enhancements, not gaps):** (a) a question prompt's "optional image"
    is satisfied by inline TinyMCE images (Cloudinary) — there is no separate figure-
    attachment field; the `QuestionImages` purpose + render path exist if one is added
    later. (b) The scenario sub-question composer offers MCQ/multi/true-false/fill-blank/
    essay; the grader also accepts matching as a sub-type, but nested matching isn't
    offered in the UI (rare authoring case).

## Section 6 — Assignments, submissions & rubric grading (2026-07-19)

1. **Assignments mirror the assessment curriculum pattern, minus placement.** An
   assignment attaches after a module's lessons (`module_id` + `position`) or sits
   standalone at the end of the outline. It joins the unified `CurriculumOutline`
   as a third item kind, so the sequential gate, sidebar and progress math all
   treat it like lessons/assessments with zero special-casing.
2. **Completion = a graded submission.** An assignment counts toward the course %
   when the student has any submission with status `graded`. Returning a graded
   version for resubmission removes that signal (and can drop a Completed
   enrollment back to Active) — the grade row itself is kept for the audit trail.
3. **Versioning is insert-only.** Every hand-in inserts `version = max + 1`
   inside a transaction; there is no update path for submission content, so prior
   versions are immutable by construction. Only `status` + the return-note audit
   fields ever change.
4. **Rubric grades snapshot their selections.** `grades.criterion_scores` stores
   {criterion id, title, level index, label, points, max} as graded, so editing or
   deleting a rubric later never rewrites a student's breakdown. The server is the
   only scorer: level choices are validated against the rubric and the total is
   recomputed; a rubric-free grade is capped at `max_points`.
5. **Publish gate for gradebook safety (per the forward note).** Publishing is
   blocked unless `max_points` > 0 and instructions are non-empty, so every
   published assignment is safe for the Section 6.5 gradebook division.
6. **Late policy is server-enforced in SubmissionService**: past `due_at` with
   `allow_late=false` throws a kind validation message; with `allow_late=true`
   the version is stored with `is_late=true` (flag is per-version, so an on-time
   v1 keeps its state).
7. **Files ride the existing media rails.** New private `AssignmentResources`
   purpose for instructor briefs; the existing `Submissions` purpose gained
   image mimes so the grading split-view can preview pdf + images inline via
   signed temporary URLs (everything else downloads). MediaPolicy delegates to
   the owning model's policy (Assignment→submit/view, Submission→view).
8. **Two new permissions** (`assignments.view`, `assignments.grade`) follow the
   assessment pair; the auditor inherits `.view` automatically and browses the
   builder, rubric library, grading workspace and versions strictly read-only.
9. **Rubrics are owner-scoped authoring tools** (instructor library, admins may
   step in) saved wholesale from a grid editor; criteria are rebuilt on save,
   which is safe because grades hold snapshots (see 4).
10. **Upload progress + draft autosave** live in `assignment-submit.js`: the form
    posts via XHR for a real progress bar (JSON redirect on success), and the
    typed answer is snapshotted to localStorage every 5s until submitted.

## Section 6.5 — Grade scales & course gradebook (2026-07-22)

1. **Gradebook items = every published, REQUIRED assessment/assignment in the
   course** — the same set that already gates course completion (Sections 5/6),
   not "every assessment" or "only ones with a result." This keeps "all required
   items graded" and "enrollment reached Completed" in permanent lock-step, which
   is what lets the completion snapshot assume a final (non-provisional) result is
   always available the instant `CourseCompleted` fires. Optional (non-required)
   assessments/assignments never enter the gradebook. A required item with no
   result yet (never attempted, or awaiting manual grading) is listed as
   **Pending** and excluded from the percentage — the two situations aren't
   distinguished, since both mean "no score to weight in yet."
2. **Percentage rounds to the nearest whole percent before mapping to a band**
   (`GradeScale::bandFor()`), not floored/truncated — a points-weighted 59.5%
   resolves the way a human reading "60%" would expect. Bands themselves stay
   integer-only (0–100), enforced by `GradeBandValidator`.
3. **Grade points and the scale limit always render to exactly one decimal**
   (`4.0`, `5.0`), never the rubric-style trailing-zero-strip used for raw points
   elsewhere — this matches every example in the brief ("82% · A · 4.0/5.0") and
   reads as a GPA figure, not a literal point count.
4. **Completion snapshot rides the same event a certificate will later hook.**
   `LearningService::recalculate()` dispatches `CourseCompleted` exactly on the
   not-Completed → Completed transition (never on a no-op recalculation); a new
   sync (non-queued) listener, `RecordCourseGradeSnapshot`, writes the
   `CourseGradeRecord` through `CourseGradeRecordService`. Section 7's
   certificate issuance listens on the same event — no new pipeline to wire.
5. **CourseGradeRecord is insert-only and versioned**, mirroring Section 6's
   submission versioning: a row is never edited after it's written. An admin
   "recompute" (gated by a plain `recompute-gradebook` Gate, not a policy —
   there's no natural model to hang it on) supersedes the current row
   (`superseded_at`) and inserts version+1. The live scale/bands are never
   re-read for an existing record — `scale_snapshot` (the whole scale: bands,
   display settings, limit) is frozen at write time, so archiving or editing a
   scale later can't rewrite history.
6. **Exactly one default scale, enforced in the service, not the DB.**
   `GradeScaleService::save()` unsets `is_default` on every other scale when one
   is checked, and silently keeps the sole existing default checked if the save
   would otherwise leave zero defaults (unchecking your only default is a no-op,
   not a validation error — there's always another scale to promote first).
   Archiving the current default is blocked outright for the same reason.
7. **The instructor matrix is batched, not per-student.** `GradebookService`
   gained `itemsForMany()` — two queries total (all attempts, all submissions)
   instead of the natural per-student `itemsFor()` calls the student-facing page
   uses — so the matrix stays N+1-free regardless of roster size.
8. **No charting dependency for the grade-distribution mini-chart** — plain CSS
   bars sized by `count / max(counts)`, consistent with the codebase's existing
   "no chart library" convention (progress bars, heat-strips elsewhere).
9. **`courses.grade_scale_id` needed adding to `Course::$fillable`** — caught by
   a feature test (the override silently no-op'd without it), not by inspection.
   Worth remembering: a new nullable FK column is easy to migrate and forget to
   also expose through mass assignment.

## Sections 4/5 — player polish: an honest "Finish course" (2026-07-23)

Manual testing of Section 6.5's completion pipeline surfaced a real, pre-existing gap
in the Section 4/5 learning player: "Finish course" only ever checked "is there a next
LESSON", so a learner who'd finished every lesson but still had an unpassed required
assessment or ungraded assignment got silently bounced back to lesson one with zero
explanation. Since these sections are already accepted and not upcoming, this was
fixed now rather than deferred, on the same branch that found it.

1. **`LearnController::congratulations()` no longer redirects on incomplete** — it
   renders a new `learn.keep-going` view: an honest, on-brand checklist naming every
   still-open REQUIRED item with a direct link to it and a plain-language reason
   ("Not passed · 44% · no attempts left", "Awaiting grading", "Not started yet").
   A genuinely complete course still gets the real congratulations page, untouched.
2. **The lesson-player's finish button is now honest about state**: "Finish course"
   only when `$snapshot->isCourseComplete()`; otherwise a secondary "Review what's
   left" button, both pointing at the same route — the destination now does the
   right thing either way, so there's no wrong button to press.
3. **`CurriculumItem` gained `statusLabel`/`statusTone`**, computed once per outline
   build in two batched queries (`LearningService::assessmentStatuses()` /
   `assignmentStatuses()` — all of a student's attempts/submissions across every
   assessment/assignment in one query each, not one per item), so the curriculum
   sidebar carries no N+1. An assessment/assignment already complete still shows a
   bare checkmark (unchanged); one that isn't now shows *why* — in progress,
   awaiting grading, needs revision, or not passed with a score and attempts left —
   instead of a flat, unchanging icon regardless of how many times it's been
   attempted. This is presentation only; nothing about scoring or completion
   changed. A failed assessment with attempts remaining always links to its normal
   start page — even fully exhausted, since that page already renders a read-only
   attempt history rather than a broken retry.
4. **No new migration.** This is a display + control-flow fix; no schema changed,
   so the append-only migration rule is unaffected.
5. **The module/course "celebration" overlay could get stuck open with no way out** —
   found live while re-testing the fix above: finishing the LAST lesson of the LAST
   module while a required assessment is still open reports `module_completed`
   (not `course_completed`), and that celebration's callback is `advance(next_url)`
   with `next_url = null` — which never navigates. `celebrate()`'s only exit had
   always been its `then` callback navigating to a new page (which incidentally
   reset the Alpine component entirely); nothing ever set `celebrating` back to
   `false` on its own. Fixed in `resources/js/learn.js`: the auto-dismiss timer now
   calls a real `dismissCelebration()` that resets state and then runs `then`, and
   the same method is wired to the modal's new close button, Escape, and a
   backdrop click — so it's never solely dependent on navigation to close.

## Section 7 — Certificates & Public Verification Portal (2026-07-24)

1. **`Certificate` is one row per (user, course), never versioned** — unlike
   `CourseGradeRecord`. The ULID `public_id` (gated download/admin routes) and the
   human `serial` (`UPRL-{YEAR}-{6 alnum}`, printed + publicly verifiable) are assigned
   once and never change, including across an admin re-issue: re-issuing re-freezes
   `snapshot` and re-renders the PDF, but a certificate already scanned/distributed
   keeps working at the same URL. `rendered_at` is null while the queued PDF job is in
   flight — the "pending" state the completion screen and My Certificates poll for.
2. **Two listeners on `CourseCompleted`, order-independent by design.** Laravel's
   listener auto-discovery does not guarantee execution order (`DiscoverEvents` uses
   `Finder` unsorted), so `IssueCertificateOnCompletion` never assumes Section 6.5's
   `RecordCourseGradeSnapshot` ran first — it calls `CourseGradeRecordService::
   recordCompletion()` itself (already idempotent) before reading the grade. It also
   wraps issuance in try/catch + `Log::error`: a certificate problem (e.g. no template
   configured yet) must never turn a student's "lesson complete" request into a 500.
3. **The snapshot freezes template config AND the grade together**, sourced from the
   just-written (or already-current) `CourseGradeRecord` — final percent, label, grade
   point, scale name/display settings — never a live re-join. Editing the grade scale
   or the certificate template afterwards changes nothing on an issued certificate;
   only an explicit admin re-issue re-freezes it (verified by test).
4. **dompdf has neither GD nor Imagick in this environment.** QR codes and the
   sunburst/diagonal-motif watermarks are generated as SVG (bacon/bacon-qr-code's SVG
   backend + hand-built SVG strings) and embedded as base64 `data:image/svg+xml`
   `<img>` tags — dompdf renders these natively via its bundled `php-svg-lib`, no
   extension required. The UPRL logo and signature images are PNG, which dompdf's CPDF
   adapter cannot embed without GD (`addPngFromFile` hard-fails) — the human enabled
   `extension=gd` in `php.ini` (present but commented out) after being asked, since a
   working PDF with the real logo is a hard acceptance criterion.
5. **Fonts are `"DejaVu Serif"`/`"DejaVu Sans"`, not `Georgia`/`Helvetica`.** dompdf's
   font registry maps unrecognised families (`Georgia`, `"Times New Roman"`) through to
   its base-14 PDF fonts (`Times-Roman`, `Helvetica`) — which are WinAnsi/Latin-1 only
   and silently render unsupported glyphs as `?`. This broke Yoruba/Igbo dotted-vowel
   names (e.g. `Ọláwálé`) on the very first pixel-check. Naming dompdf's actual bundled
   Unicode TTFs directly fixes it completely and satisfies the brief's own "embedded
   fonts" requirement more literally than the base-14 fonts ever could.
6. **Certificate templates are plain absolute-position CSS, never `display:table`
   nested inside `position:absolute`.** An early layout used a table-cell signature/
   footer row and it rendered inconsistently under dompdf; every row is now two
   independently `position:absolute` boxes instead. Also: `.sheet` (the A4 canvas) may
   carry `overflow:hidden` but must NOT also carry its own `padding` under
   `box-sizing:border-box` — that silently shrinks the usable canvas height and was the
   actual cause of a phantom blank second page under a long course title, not the
   overflow itself.
7. **Signature images upload immediately via AJAX** (`certificate-templates/signature-
   upload`, purpose `Signatures` — already provisioned in Section 0.5's `config/
   media.php`), the same "upload now, submit the Media id later" pattern as TinyMCE's
   editor images. `CertificateTemplateService::save()` attaches the new image via
   `HasMedia` and deletes any image it replaces, so a template never accumulates
   orphaned signature files.
8. **Public verification never shows the grade, and a revoked result never shows the
   reason** — a certificate proves completion, not a transcript, and a revocation's
   internal reason (e.g. an integrity finding) is exactly the kind of detail that
   should stay internal. A miss (`not_found`) uses identical, generic copy regardless
   of why the serial didn't match, so probing can't fingerprint the serial format.
   `/verify*` is rate-limited (`throttle:30,1`) — Laravel's built-in limiter, not a
   bespoke one; sufficient for "basic rate limiting" against serial-guessing.
9. **Permissions split three ways**, mirroring Section 6.5's grade-scale precedent:
   `certificate-templates.manage` (admin-only design work, no auditor — same reasoning
   as `grade-scales.manage`), `certificates.view` (`.view`-suffixed ⇒ the auditor
   inherits it automatically, browses the registry read-only) and `certificates.manage`
   (admin-only issue/re-issue/revoke/restore). A student's own certificate access is
   ownership-checked in `CertificatePolicy`, not permission-gated.
10. **Certificate templates seed BEFORE any course/enrolment/progress seeding**, not
    alongside Section 6.5's `GradeScaleSeeder` at the end. Every genuine course
    completion elsewhere in `DatabaseSeeder` (ProgressSeeder's finished PRL101
    students, GradeScaleSeeder's own completer) then issues a real certificate through
    the normal event pipeline — no hand-rolled demo `Certificate::factory()` rows
    needed for the common case. A final `CertificateSeeder` only tops up to three if
    the natural pipeline produced fewer, and revokes the earliest-issued one so every
    state (valid/pending/revoked, with/without a grade) is demonstrable.

## Section 8 — Notifications (Email + In-App) (2026-07-24)

1. **Services notify directly; no new event classes.** The one pre-existing precedent
   (`CourseCompleted` + auto-discovered listeners) is kept for course completion —
   `CertificateIssuedNotification` fires from inside `CertificateService::issue()`,
   which the completion listener already calls. Everywhere else (enrolment
   approve/reject/confirm/waitlist, course submit/publish/return, assignment grade/
   return, attempt finalise, new submission, bulk import) the notification is sent from
   the **same service method that already owns the state write** (`EnrollmentService`,
   `CoursePublishingService`, `AssignmentGradingService`, `AttemptService`,
   `SubmissionService`, `ProcessEnrollmentImport`). Adding ~10 event+listener pairs
   purely to relay a `->notify()` would be indirection without payoff — the services are
   already the single, tested choke points for each action.
2. **One base `UprlNotification` centralises channel routing.** Every catalogue class
   extends it and implements only `type()` + `toMail()` + `toArray()`. The base `via()`
   reads the recipient's per-type preferences (`User::notifiesInApp/notifiesByEmail`) and
   withholds immediate mail for a digestible type when the user has opted into the daily
   digest — so a new notification honours preferences and digest batching for free.
3. **Preferences ride the existing `learning_preferences` JSON column**, not a new table.
   Section 1 already had `email_digest` there; Section 8 adds a `notifications` sub-map
   (`type => {email, in_app}`) via `User::setNotificationPreference()`, always merging so
   unrelated keys are never clobbered. No migration for preferences.
4. **Critical types are in-app-locked by construction.** `NotificationType::isCritical()`
   (approvals, rejection, waitlist promotion, certificate) forces `via()` to include
   `database` regardless of preference, and the profile matrix renders their in-app toggle
   disabled+checked. `ProfileController::updateNotifications()` skips critical types
   entirely, so a crafted POST can't disable them (covered by test).
5. **Digest = a scheduled command reading real DB rows, keyed by `digested_at`.**
   `notifications:digest` (daily) folds every un-digested, digestible database-notification
   for each digest-opted user into one `DailyDigestNotification` (mail-only), stamping
   `digested_at` so a row is bundled exactly once. The migration adds `digested_at` to
   Laravel's standard notifications table — the one deviation from the framework default,
   documented here.
6. **Due-soon and pending-enrolment digests each get their own idempotency flag, not a
   time-window guess.** `notifications:due-soon` (hourly) inserts an
   `assignment_due_reminders` row per (assignment, user) it reminds, so re-running any
   hour never double-reminds and a student who already submitted is skipped.
   `notifications:pending-enrollment-digest` (every 15 min) stamps
   `enrollments.pending_digested_at`, batching all new pending requests per course into
   one instructor notification; a re-enrolment resets the flag so it's re-reported.
   Scheduling lives in `routes/console.php` (Laravel 12 bootstrap style — no `Kernel.php`).
7. **The bell is polled, not websocketed.** `notification-bell.js` fetches
   `/notifications/recent` on open + every 60s. Websockets (Reverb/Pusher) are noted here
   as the future upgrade; polling is sufficient and adds no broadcast infrastructure now.
8. **Icon + tone are derived from the notification's stored `type` class, never stored on
   the row.** `NotificationType::icon()/tone()/toneClasses()` are the single source; the
   controller resolves them for the bell JSON and the Blade page resolves them per row. So
   restyling a type's colour/icon needs no data migration. The bell dropdown shows a bell
   glyph in a tone-tinted tile (Alpine can't swap a compiled SVG per row); the full
   `/notifications` page — a real Blade iteration — shows each type's actual icon, and
   groups rows into a Today/Yesterday/weekday/date timeline.
9. **Announcements are a first-class model with a course-scoped policy.**
   `CourseAnnouncementPolicy` (view = enrolled-or-staff, manage = course owner) is
   registered as named gates like `EnrollmentPolicy`'s course-scoped abilities. The body
   is a `RichHtml` field (sanitised on save, rendered through `<x-ui.prose>`); posting
   notifies every active/completed student via their preferences.
10. **Section-8 UI reuses the established brand system, not a new identity** — the same
    crimson/Fraunces/gold tokens, `<x-ui.empty-state>` sunburst, `<x-dropdown>`,
    `<x-ui.avatar>` and skeleton primitives as the prior seven sections, so the bell,
    notifications timeline, announcements and preference matrix read as one designed
    product. A `NotificationSeeder` tops up a varied, time-spread showcase inbox for the
    demo student so the timeline is convincing on a fresh seed.

## Section 8 — Nigerian demo data (2026-07-24)

Per the human's request that the seeded demo read as authentically Nigerian rather than
generic `fake()` output (foreign names, Latin lorem):

1. **`Database\Seeders\Support\Nigeria`** is a curated reference-data helper (autoloaded
   under the existing `Database\Seeders\` PSR-4 root, so no `composer.json` change). It
   holds first names and surnames across the three largest ethnic groups (Yoruba, Igbo,
   Hausa/Fulani) and pairs within a single group per person, so names read coherently
   (`Adebayo Ogunleye`, `Chidi Okafor`, `Ibrahim Bello`) — plus `+234` phone numbers and
   academic titles.
2. **Wired into `UserFactory`, `UserInvitationFactory` and `DatabaseSeeder`** (instructors
   are fixed, recognisable names; students are generated). Every seeded account also gets a
   Nigerian phone; instructors get a title. This also improves test readability at zero
   behavioural cost.
3. **Real English replaces Latin lorem in visible demo content**: text-lesson bodies
   (`CourseSeeder::lessonBody()`, on-topic PR/leadership prose) and the volume-padding
   question prompts (`QuestionFactory` now draws from a real PR/leadership prompt pool).
   Hand-authored seeder content was already real and untouched.
4. **A latent flaky test was hardened, not worked around.** `EnrollmentApprovalTest`'s
   `assertDontSee($otherStudent->name)` implicitly relied on Faker's near-unique names; the
   smaller curated pool raised collision odds and surfaced it. Fixed by giving those two
   students explicit distinct names — the test controls its identifying text rather than
   trusting randomness.

## Section 9 — Communication: Course Forums & Messaging (2026-07-25)

1. **Three-tier forum authorization, all delegating to the course.** A course-scoped
   `ForumPolicy` registers three named gates — `accessForum` (read: any member, plus
   staff/auditor who may view the course), `participateInForum` (open threads & reply:
   members who may write; the read-only auditor is explicitly excluded), and
   `moderateForum` (pin/lock/remove/answer-as-staff: the same ownership rule as editing
   the course). Per-instance rules live in auto-discovered `ForumThreadPolicy` /
   `ForumPostPolicy` (a locked thread rejects new replies except from moderators; the
   thread author or an instructor may accept an answer; the author or a moderator may
   remove a post). This mirrors the existing `EnrollmentPolicy`/`CourseAnnouncementPolicy`
   named-gate pattern rather than inventing a new one. **Membership** is a new
   `Course::isMember()` (enrolled active/completed, or an instructor) with `members()` /
   `enrolledStudents()` helpers.
2. **Answered = a pointer, not a flag.** `forum_threads.answer_post_id` references the
   accepted `forum_post` (added in a follow-up migration once `forum_posts` exists, so the
   FK has a real target); its presence *is* the "Answered" state, so there's no separate
   boolean to keep in sync. Soft-removing the answer post clears the pointer in
   `ForumService` (and the FK's `nullOnDelete` covers a hard delete). Replies are capped at
   one level: replying to a reply re-parents to its top-level post.
3. **Direct conversations are canonical.** `MessagingService::startDirect()` finds the one
   existing two-person direct thread (by participant match + count) or creates it, so
   "Message instructor" and replying always land in the same conversation — never a
   duplicate. Group conversations are staff-only (`createGroupConversation` gate); "message
   all enrolled" reuses one durable group thread per course (`conversations.course_id`),
   topping up membership on each broadcast so it stays a single thread.
4. **Who-may-message-whom is service logic, not a policy.** Participation
   (`ConversationPolicy`) gates every read/write once a conversation exists; *starting* one
   is gated by `MessagingService::canMessage()` — staff may reach any of their course
   members, everyone else must share a course. This keeps the "shared course" business rule
   in one testable place rather than smeared across policies.
5. **Unread is derived from a per-participant watermark, never stored.** `conversation_user.
   last_read_at` is the read line; unread counts come from a single grouped query joined to
   it (`MessagingService::unreadCounts` / `totalUnread`), and opening a conversation stamps
   the watermark. The bell integrates for free: a `NewMessageNotification` (a normal
   Section-8 `UprlNotification`, honouring channel preferences) is sent to *caught-up*
   participants only — someone who already has an unread in that thread isn't re-pinged, so
   a burst doesn't spam the bell (the spec's allowed dedupe).
6. **Messaging & forum bodies use the existing rich-text + sanitisation stack.** Every body
   is a `RichHtml::class.':basic'` cast (the `basic` purifier profile the constitution had
   already earmarked for "forums / messages" — text formatting only, no images/tables),
   authored through `<x-ui.rich-editor profile="basic">` and rendered via `<x-ui.prose>`.
   Tests assert `<script>` is stripped from both. A new `.uprl-prose-invert` modifier keeps
   links/quotes legible in the crimson own-message bubble.
7. **Message attachments reuse the private-file plumbing.** A new
   `MediaPurpose::MessageAttachments` (private disk, validated mimes/size in
   `config/media.php`) stores a single optional attachment via the existing
   `PrivateFileService` + `HasMedia`, served only through the policy-gated `media.download`
   route — `MediaPolicy::view` now allows any participant of the owning message's
   conversation. No public CDN URL, consistent with submissions/certificates.
8. **Moderation hooks, not moderation AI.** Reporting a post (`forum_post_reports`, one flag
   per user per post) feeds an admin-only review queue (`reviewForumReports` gate) where an
   admin dismisses the flag or removes the post (which resolves its reports). **Profanity
   filtering is explicitly out of scope** per the brief; the report queue and the
   `RichHtml` sanitiser are the moderation surface. Posting is rate-limited (`throttle`
   middleware on thread/reply/message/broadcast routes) so a runaway client can't flood.
9. **Forum lives under `/courses/{course}/forum`, not `/learn/...`.** The learning player's
   two-segment catch-all `/learn/{course}/{lesson}` would shadow a two-segment
   `/learn/{course}/forum`; routing the forum under the (public-catalogue) `/courses` prefix
   at three segments sidesteps the collision entirely while staying course-scoped by slug.
   "Discuss this lesson" from the player deep-links to `forum.index`/`forum.create` with a
   `lesson=` scope. A `chat` / `chat-group` icon pair was added to `<x-ui.icon>`.
