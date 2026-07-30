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

## Section 10 — Reporting, Analytics & Exports (2026-07-25)

1. **One report abstraction drives preview + all three export formats.** Each report
   (`App\Reports\*`) implements a single `Report` contract that returns positional row
   arrays aligned 1:1 with its `headings()`. The on-screen preview, the xlsx/csv
   spreadsheet (`ReportExport`) and the branded PDF (`ReportPdf` → one shared
   `reports/pdf/layout` view) all consume the *same* rows, so what the Vice-Chancellor
   prints matches the admin's screen exactly — no drift. Query-backed reports extend
   `EloquentReport` (count/paginate/rows derived from a `baseQuery` + a chunk-mapper that
   batch-loads grade snapshots/certificates, N+1-free); the two aggregate reports
   (instructor, compliance) implement the contract directly with in-memory paging.
2. **Learner grade columns come from the immutable `CourseGradeRecord` snapshot, never a
   live re-derivation.** They therefore match the gradebook and the printed certificate
   exactly, and a learner with no completed course shows a tidy empty cell — never a
   misleading `0` (asserted in tests). This also avoids running the gradebook aggregation
   per enrolment across a large report.
3. **Compliance "department cohort" is a documented proxy.** Users have no direct
   department (only courses do), so a department cohort = everyone enrolled in that
   department's courses; the e-mail cohort matches users by (case-insensitive) address and
   reports unmatched addresses in the filter echo. Rows are the cohort × target-courses
   cross-product resolved against one preloaded enrolment map (no per-cell query);
   status is Completed / In progress / Never started with headline percentages.
4. **Exports > 2 000 rows are queued, not streamed.** `ReportController@export` streams
   small reports inline; over `ReportExporter::QUEUE_THRESHOLD` it creates a
   `generated_reports` row, dispatches `GenerateReportExport`, and tells the admin a
   notification will carry the link. The job builds the file on the **private** disk and
   fires `ReportReadyNotification` (new `NotificationType::ReportReady`, in-app only —
   it answers the user's own action so it bypasses per-type channel prefs). Download is a
   Policy-gated route (`GeneratedReportPolicy` — owner or super-admin), never a public URL,
   consistent with the constitution's treatment of sensitive generated files.
5. **The Section-6.5 gradebook CSV is folded into this path.** The bespoke `GradebookExport`
   is deleted; `GradebookController@export` now routes through `ReportExporter::downloadRows`
   and gains xlsx/pdf alongside csv, removing the duplication the brief called out.
6. **Charts: Chart.js, lazy-loaded, brand palette from CSS vars in one place.** A
   `<x-ui.chart>` component emits the config as JSON; `resources/js/charts.js` dynamically
   imports Chart.js only where a chart exists (mirrors the TinyMCE pattern — kept out of the
   main bundle) and resolves named tones (`crimson`, `green`, `gold`, band tone names) to
   the live `--uprl-*` custom properties. Those tokens are stored as bare `R G B` channel
   triplets (for Tailwind's opacity modifiers), so the resolver wraps them as `rgb(R G B)` —
   the one subtlety needed to make brand colour render on a canvas. `prefers-reduced-motion`
   disables animation.
7. **Admin dashboard aggregates are cached 5 min; per-user views read live.** Platform-wide
   figures (stats, 12-month trend, top courses) that sweep whole tables are memoised;
   instructor/student dashboards and the activity feed read live so a learner never sees a
   stale progress bar. The month-bucket trend query uses a driver-portable year-month
   expression (sqlite/mysql/pgsql). New indexes (`add_reporting_indexes`) back the trend,
   certification, assessment-analytics and active-users queries; the warm admin dashboard
   stays within the ~15-query budget (asserted).

## Sections 0–10 — Full demo QA pass & fixes (2026-07-25)

A complete pre-demo audit: 447 tests green, every page crawled as all five roles (no 500s;
every 403 correct by design), and the headline interactive flows driven end-to-end in a real
browser. Five genuine defects were found that no test covered, because each one lived in a
seam the suite doesn't reach — seeded data shape, an Alpine expression, and a JSON response
shape. All are fixed on this branch.

1. **`ReportingDemoSeeder` was writing enrolments straight past course capacity.** It creates
   rows with `Enrollment::create()` rather than through `EnrollmentService`, so PRL305 (cap 3)
   ended up with **10 active seats** — the roster meter printed "10 / 3", and, far worse, the
   Section-3 waitlist auto-promotion demo was silently dead: withdrawing a student freed
   nothing because the course was still over capacity, so nobody was ever promoted. The seeder
   now tracks seats in memory and only adds a seat-holding (active) row while a seat is free;
   `completed` rows release their seat and stay unrestricted, so the trend/top-courses/
   completion-rate figures are unaffected. Documented as invariant #1 in the class docblock.
2. **`EnrollmentSeeder` over-filled LDS110 for the same reason** (pre-existing, unrelated to
   Section 10): it seeded 5 active + 1 pending against a capacity of 5. A *pending* request
   holds a reserved seat (`EnrollmentStatus::occupiesSeat()`), so that is 6/5. Reduced to 4
   active + 1 pending — the course is still exactly full, which was the seeder's stated intent,
   and approving the pending request keeps it at 5/5.
3. **Back-dated enrolments had a cached `progress_percent` with nothing behind it.** A
   "completed" historical enrolment showed 100% while its lesson-progress heat-strip was empty,
   so the instructor progress page and the course analytics contradicted the roster. The seeder
   now bulk-inserts matching `lesson_progress` rows (with `seconds_spent`, so time-spent columns
   read sensibly too). Deliberately NOT replayed through `LearningService` — these are
   historical records, and the live pipeline would re-fire course completion, re-issue
   certificates and re-notify for events dated months ago.
4. **The "Withdraw" button on My Learning did nothing at all.** Alpine only wraps a
   statement-style expression in an async IIFE when `/^[\n\s]*if.*\(.*\)/` matches, and `.`
   never matches a newline — so the multi-line `@submit.prevent="if (await window.uprlConfirm({…`
   in `learning/index.blade.php` was a silent `SyntaxError` and the form never submitted. Every
   other `uprlConfirm` call site in the codebase is single-line and was unaffected. Fixed by
   collapsing it to one line, with a comment recording why it must stay that way. **Worth
   remembering: an Alpine expression starting with `if (` must never be wrapped across lines.**
5. **The notification bell crashed when its poll failed.** `fetchRecent()` assigned
   `data.notifications` unconditionally; an expired session answers with a JSON body that parses
   fine but has no `notifications` key, so `notifications` became `undefined` and every
   `notifications.length` binding in the dropdown threw. Now checks `r.ok` and coerces both
   fields defensively.
6. **Padded question-bank items showed placeholder answers.** Section 8 replaced Latin lorem in
   the *prompts* but left `QuestionFactory`'s options as `Correct` / `Wrong A` / `Wrong B` — so
   the seeded Final exam rendered a real question above options whose correct answer was
   literally labelled "Correct", and `fillBlank`/`matching` padding carried unrelated
   geography answers under PR prompts. Each factory state now draws a prompt and its own
   answers *together* from an on-topic pool, so a padded item is internally coherent. Option ids
   and which id is correct are unchanged, so the 57 assessment tests pass untouched.

### Also changed

7. **The queued-export threshold is now config-driven** (`config/reports.php` →
   `reports.queue_threshold`, env `REPORTS_QUEUE_THRESHOLD`, default unchanged at 2 000).
   `ReportExporter::QUEUE_THRESHOLD` stays as the default constant; `ReportExporter::
   queueThreshold()` is what the controller reads. Ops can tune where "large" starts without a
   deploy, and — the immediate reason — the queued path is otherwise undemonstrable on any
   realistic demo dataset without manufacturing thousands of throwaway enrolments.
8. **Local `.env` switched to `MAIL_MAILER=log`** (human approved). With
   `QUEUE_CONNECTION=sync`, every notifying action was blocking on a live SMTP handshake:
   ~1.5s per action, `NotificationSeeder` alone taking 35–48s, and a hard failure anywhere
   port 465 is blocked. Log driver makes actions instant and network-independent; seeding drops
   to ~4s. `.env` is untracked, so this is a local-machine change only — production
   configuration is unchanged.
9. **`docs/UPRL-LMS-Demo-Guide.pdf`** (+ its `docs/demo-guide.html` source) is the current
   presenter runbook, covering Sections 0–10. It supersedes `docs/demo-script.md`, which only
   covers Sections 0–4 and is now stale — kept for now rather than deleted, since removing a
   file is the human's call.

## Section 11 — Programmes & Parts (2026-07-29)

The NIPR curriculum classifies courses as **Programme → Part → Course** (CPR Part I/II,
DPR Part I/II, Professional Variant Parts 1–3). The repo already classified them as
Faculty → Department → Course.

1. **Both hierarchies are kept; they are orthogonal.** Faculty/Department answers *who
   owns and teaches this course*; Programme/Part answers *what qualification it counts
   toward and what it costs*. Replacing one with the other would have thrown away real
   information either way. Programme is a second axis, filterable independently on the
   catalogue.
2. **Course ↔ Part is many-to-many, and had to be.** The published schedule lists CPR 112,
   CPR 115, CPR 216, CPR 219 and DPR 411 under **two** programmes each. A `courses.part_id`
   column cannot express that. `credit_load` and `requirement` live on the
   `course_programme_part` pivot for the same reason — they are properties of the
   *placement*, not the course: the same paper carries 3 credits and "Compulsory" in CPR
   Part I, and no stated credit at all in Variant Part 1.
3. **`is_primary` on the pivot exists to resolve price.** A dual-placed paper sits in
   programmes with different per-paper fees (CPR ₦7,000, Variant ₦15,000) and Section 12
   needs one answer. Exactly one placement per course is primary; dual-placed papers keep
   their *home* programme, so CPR 112 prices at ₦7,000. The invariant is enforced in
   `Course::syncProgrammePlacements()` rather than by a partial unique index, because
   partial indexes are not portable to the sqlite the test suite runs on. The service
   normalises: first claimant wins, and if nothing claims it the first row is promoted, so
   `primaryProgramme()` is never null for a placed course.
4. **Fee columns landed on `programmes` now, in Section 11.** They are Section 12's
   concern, but the published schedule prices by programme tier and the admin screen would
   have been dishonest without them. Nothing reads them yet beyond display.
5. **`credit_target` records what the prospectus *states*, which is not the sum of the
   listed credits.** CPR Part I lists 28 credits but prints "Total 24". The 4-credit gap is
   exactly CPR 118 (2) + CPR 119 (2) — its only two *pure* electives. GNS is inside the
   stated 24, and DPR Part I (18 compulsory + 3 required elective = 21) confirms required
   electives count. Hence `CourseRequirement::countsTowardTarget()` excludes **only**
   `Elective`. The admin screen shows counted, listed and target side by side rather than
   silently picking one and looking wrong against the printed prospectus.
6. **Programme slugs come from the code, not the name.** `/courses?programme=cpr` is what
   the prospectus, the staff and the reference site all call it;
   `professional-certificate-in-public-relations` is unusable in a filter URL. Codes are
   already unique and validated `alpha_num`, so they are URL-safe.
7. **Part slugs are unique per programme, not globally.** Every programme has a "Part I".
   Consequently `ProgrammePart` is *not* route-keyed by slug, and the catalogue ignores a
   `part` filter that arrives without a `programme` — a bare `part-i` would otherwise match
   three different programmes at once.
8. **Only the three programmes the fee schedule prices were seeded from it** (CPR, DPR,
   NPV), plus a **Master Class (NMC)** with all fees at 0. The reference site shows a
   Master Class but the schedule states no courses or fees for it, so nothing was invented;
   an admin adds real ones from the Programmes screen.
9. **The eight hand-written demo courses go in the Master Class, not in CPR/DPR.** Placing
   them in the examined parts pushed CPR Part I from 24 counted credits to 29 and DPR Part I
   from 21 to 24, so the admin screen flagged a mismatch on a curriculum that is in fact
   transcribed correctly — the *seed data* was lying about the prospectus. NMC's zero fees
   also keep those eight courses free, so every pre-Section-11 demo flow is unchanged.
10. **CPR 115's blank status column is read as Compulsory.** The source leaves it empty. It
    is consistent with every other 3-credit CPR Part I paper, and the stated 24-credit total
    only reconciles if 115 is inside it. The Professional Variant publishes neither credits
    nor status, so its placements carry a null credit load and are marked compulsory — the
    Variant is a fixed syllabus, there is nothing to choose.
11. **Instructors hold `programmes.view`, not `programmes.manage`.** An instructor files
    their own course into an existing part from the course builder; the structure itself
    decides what a qualification means and what it costs, so it stays an admin concern.

### Fixed along the way

12. **The catalogue had two pre-existing N+1s**, both on the list page this section extends,
    and the Definition of Done forbids them. `HasMedia::firstMediaFor()`/`mediaFor()` always
    ran a fresh query, so the controller's eager-loaded `media` was fetched and then thrown
    away — one query per cover image. The card's `total_duration` alias was never populated,
    so it fell back to a per-course `SUM`. Instructor avatars were a third (`instructors`
    was loaded, `instructors.media` was not). Fixed by reading the loaded relation in the
    trait and aggregating with `withSum` in the controller: **42 queries → 16**, and now
    flat regardless of filter or page size.

## Section 12 — Commerce: pricing, cart, checkout, coupons, payments (2026-07-30)

The LMS had no commerce at all — every course was free and
`catalogue/_enrol.blade.php` hardcoded "Free for UPRL learners". This section adds
paid courses, a cart, checkout, discount codes and admin-managed payment gateways.

### Money

1. **Price is DERIVED, not stored per course.** Resolution order:
   `is_free` → `price_override` → primary programme's `per_paper_fee` → 0. The published
   NIPR schedule prices by TIER (CPR ₦7,000, DPR ₦10,000, Variant ₦15,000), so copying a
   price onto ~40 courses would be 40 numbers to keep in step with 3. Section 11's
   `is_primary` placement is what makes this unambiguous for a paper listed in two
   programmes.
2. **`courses.is_free` defaults to TRUE.** Every course that already existed, and every
   course any existing factory or test creates, keeps behaving exactly as before.
   Charging is opt-in — the safe direction for a default. The model also declares
   `$attributes = ['is_free' => true]`, because without it a freshly created
   (un-refreshed) instance reads `null` and would be priced from its programme's fee
   when the stored row says otherwise.
3. **Programme entry fees are CART-level, charged once ever.** Registration and
   administration are the Institute's charge for entering a programme, not a course
   price, so they are computed per cart from what the buyer has already paid
   (`PricingService::entryFeeLinesFor`). A second CPR paper costs ₦7,000, not ₦52,000.
   Free courses never trigger them — nobody should be asked for ₦45,000 to start a free
   taster.
4. **Coupons never discount entry fees.** A "100% off" code makes the *paper* free and
   leaves registration and administration standing. `CouponType::Full` is its own case
   rather than "100 percent" so a free-access code cannot be defeated by rounding.
5. **A fixed coupon never exceeds the eligible amount** — a ₦50,000 code against a
   ₦10,000 cart discounts ₦10,000. A total can never go negative and the institution can
   never end up owing a student money.

### Security

6. **The paywall is one line in one place.** `EnrollmentService::selfEnroll` refuses a
   paid course without a paid order. Every button, badge and cart page is presentation
   on top of that; a student who POSTs straight at the enrol endpoint is refused there.
   Purchases enter through `adminEnroll` (called by `OrderFulfilmentService`), which
   deliberately does not run the check.
7. **Money never comes from the request.** `CheckoutService::place()` re-resolves every
   price from `PricingService` inside the order transaction. Posted totals, subtotals
   and line prices are not validated — they are ignored. Cart rows snapshot a price
   only so the cart reads consistently between page loads; a stale or tampered snapshot
   cannot change what someone is charged.
8. **Webhooks are signature-verified and re-verified.** The endpoint is public and
   unauthenticated, so `PaystackGateway` checks an HMAC-SHA512 of the raw body with
   `hash_equals`, and the body is only ever trusted to say WHICH order to look at.
   Whether it was paid, and for how much, is re-read from Paystack's own API and
   compared against our order total — a settled amount that does not match refuses to
   settle. CSRF is exempted for `webhooks/payments/*` in `bootstrap/app.php`.
9. **Fulfilment is idempotent by construction.** `markPaid()` re-reads the order under
   `lockForUpdate` and short-circuits if already paid, so two concurrent webhooks cannot
   both transition it. `coupon_redemptions` is unique on `(coupon_id, order_id)`, so a
   replay cannot burn a second use of a code at the database level rather than relying
   on application care.
10. **Gateway credentials are `encrypted:array` on the model.** Secrets are never at
    rest in plaintext, never in a database dump, and are never rendered back to the
    admin form. A blank submitted secret therefore means "unchanged", not "clear it" —
    otherwise changing a label would silently break a working gateway. Only keys the
    driver declares in `config/commerce.php` are accepted, so a crafted post cannot
    stuff arbitrary data into the encrypted column.
11. **Payment methods are admin-only and NOT extended to the auditor.** "Read-only
    observer" should not include reading payment secrets.

### Design

12. **Credentials live in the database, not env.** Staff rotate keys, not deploys: an
    admin pastes a new secret and switches Test → Live from the Payment methods screen.
    The env values are seed defaults for a fresh install only.
13. **Three drivers behind one interface.** Sandbox (instant success — what makes the
    whole flow demonstrable and testable with no merchant account, and what the suite
    exercises so the tests drive the real CheckoutService rather than mocks), Paystack
    (real HTTP, no package — three endpoints do not justify a dependency, and the
    Laravel 12 package landscape is unsettled), and Bank transfer (never returns `paid`;
    only a human looking at a statement can know a transfer landed).
14. **A zero-total order still becomes a real paid order** so the receipt, the enrolment
    and the history all exist — it just skips the gateway.
15. **The guest cart is keyed on the session AND a long-lived cookie.** The session is
    primary; the cookie is a mirror so a visitor returning next week still finds their
    basket after a 120-minute session has expired. `MergeGuestCart` folds it into the
    account on login, synchronously — the very next request is usually the cart, so a
    queued listener would race the redirect and show an empty one.
16. **Refunds are recorded, not executed.** `markRefunded` writes our books; the money is
    returned by a human in the provider's dashboard or the bank. The enrolment is
    deliberately left in place — a student's completed work and grades are not ours to
    delete as a side effect of a bookkeeping entry.
17. **Instructors may issue course-scoped codes only.** Scope is the authorization
    boundary: an instructor grows their own enrolment, while global and programme-wide
    codes cost the institution money across a catalogue and stay admin-only. Instructors
    need no `coupons.manage` permission — their authority comes from teaching the course.
18. **A code's `code` and `scope` are immutable once created.** Somebody may already be
    holding the code, and re-scoping a live code silently rewrites what past redemptions
    meant. Either change is a new coupon. A code that has been redeemed is deactivated
    rather than deleted, so the ledger keeps pointing at something real.

### Found while building

19. **`POST /cart/coupon` matched the `/cart/{course}` wildcard.** Literal segments must
    be declared before the wildcard or the coupon endpoint resolves a course whose slug
    is "coupon". Caught before it shipped; the route file now says so.
20. **`used@if (...)` is not a Blade directive.** Blade requires a non-word character
    before `@`, so `used@if` parsed as literal text and left an orphaned `@endif`,
    500-ing the coupons index and edit screens. It reached a browser because the admin
    tests asserted redirects and database rows without ever RENDERING those views —
    render assertions have been added for both.
21. **Reaching `/checkout` with an emptied cart said "Your cart is empty."** When the
    cart had just been pruned because the buyer already owned everything in it, that
    reads like the site lost the basket. It now says so.

## Section 13 — Public homepage & guest journey (2026-07-30)

1. **`/` becomes a controller, and the page becomes real data.** The route closure
   returning a static `welcome.blade.php` (which carried its own duplicate `<html>`
   shell) is replaced by `HomeController` on `<x-public-layout>`. Every figure, card and
   link on the page now comes from the database — a marketing page that lies about the
   catalogue is worse than no marketing page.
2. **One read-only `PublicSiteService`, not four controllers doing queries.** The
   homepage, `/programmes` and `/programmes/{programme}` all read through it. Nothing in
   it writes, so a stranger browsing the public site never mutates a row.
3. **The stats band is one query and is cached for five minutes.** The four figures are
   correlated subqueries in a single `SELECT`, so the band costs one round trip rather
   than four full table sweeps per anonymous hit. A learner count that is five minutes
   stale is fine; the price on a course card is not, which is why the featured rail is
   deliberately **not** cached.
4. **"Learners" counts distinct people who took a seat.** `count(distinct user_id)` over
   `active` + `completed` enrolments: one student on six papers is one learner, and a
   pending or rejected application is not a learner at all.
5. **Round the band down, don't print a precise figure.** "40+ courses" reads as a claim;
   "43 courses" reads as a number that will be wrong tomorrow. Figures under ten stay
   exact, because "0+ learners" is absurd.
6. **The programme page shows only catalogue-visible courses, and sums the credits from
   those same rows.** A programme page must not become a way to enumerate drafts. The
   consequence is deliberate: if a paper in the published curriculum is still a draft, it
   is absent from the list AND from the total, so the two always reconcile with each
   other. `ProgrammePart::creditsCounted()` already accepted a collection for exactly
   this. An inactive programme 404s, so switching one off in admin removes it from the
   public site the same second.
7. **`GET /checkout` left the `auth` group.** A signed-out buyer now sees their priced
   order with an inline "Log in to continue" panel (`commerce/checkout-guest.blade.php`)
   instead of being redirected to a bare login form having lost all context. The
   intended URL is recorded there, so logging in returns them to the checkout with the
   cart merged. Everything that WRITES — `POST /checkout`, the callback — is still
   auth-gated, and `Commerce\CheckoutTest` was updated from "a guest cannot reach
   checkout" to "a guest cannot place an order", which is the rule that actually matters.
8. **"Buy now" now takes a guest to the checkout too.** Section 12 sent them to the cart
   to avoid a login wall; with the wall gone, "buy now" can mean buy now.
9. **A second focus-ring token, `focus-ring-inverse`.** The shared `.focus-ring` is
   crimson with a surface offset — invisible on the crimson marketing heroes, where a
   keyboard user would have had no focus indicator at all. Hero controls use the
   inverted (white ring, crimson offset) variant. One token, defined beside the other in
   `resources/css/app.css`.
10. **No new migration, no new seeder.** The section adds no schema and no data of its
    own: it surfaces what Sections 11 and 12 already seed. `migrate:fresh --seed` yields
    a homepage with four real programmes, 46 priced papers and real enrolment counts.

### Found while building

11. **The public header crushed the logo to a sliver at 375px.** The lockup had no
    `shrink-0`, so flex took the space the right-hand nav wanted and "Log in" wrapped
    onto two lines. Pre-existing on every `<x-public-layout>` page, surfaced by this
    section's 375px pass: the mark now steps down a size on small screens instead of
    being squeezed, and the nav labels are `whitespace-nowrap`.
12. **`User::role()` throws when the role does not exist.** Spatie's scope raises
    `RoleDoesNotExist` rather than matching nothing, which 500-ed the homepage on any
    install whose roles were not seeded. The instructor count is a plain
    `whereHas('roles', …)` instead. A public homepage must not depend on seed order.
13. **Desktop column widths leaked into the mobile stack.** The programme curriculum rows
    are a table from `sm` and stacked cards below it; an unprefixed `w-14` on the credits
    cell wrapped "2 credits" onto two lines at 375px. Every fixed width is now
    `sm:`-prefixed, and the three metadata cells collapse to one line via `sm:contents`.
14. **`tests/Feature/ExampleTest`'s `GET /` needed a database.** The scaffolding smoke
    test ran without `RefreshDatabase` because the old homepage queried nothing. It now
    uses it; the homepage's real behaviour is covered in `Tests\Feature\Public`.
