# Hardening report — Section 15

A security, performance and accessibility sweep over the whole application as it stands
after Sections 0–14, including the commerce and payments surface (Sections 11–12), the
public marketing site (Section 13) and the unified curriculum (Section 14).

**Every finding below is either fixed or explicitly accepted with a stated reason.**
Nothing is listed as "to do".

Findings are numbered `H-n`. Where a finding is covered by a test, the test is named —
the point of the sweep is that these cannot silently regress.

---

## Summary

| | Found | Fixed | Accepted with reason |
|---|---|---|---|
| Security | 12 | 10 | 2 |
| Performance | 5 | 5 | 0 |
| Accessibility | 4 | 1 | 3 (verified already correct) |
| Correctness (found while sweeping) | 4 | 4 | 0 |

Route-permission map: **143 mutating routes, 0 unguarded.**
Dependency advisories: **14 found, 0 remaining.**

---

## Security

### H-1 — Event listeners were registered twice (FIXED) — *high*

Laravel 11+ enables listener auto-discovery by default (`Application::configure()` calls
`withEvents()`), which binds any `handle*` method in `app/Listeners` to its type-hinted
event. `MergeGuestCart` was **also** registered explicitly in `AppServiceProvider`, so it
ran **twice on every single login**.

It survived unnoticed because merging an already-merged cart happens to be idempotent.
The audit listener added in this section was not so forgiving — it recorded two entries
for one sign-in, which is how the bug was found.

**Fix:** discovery switched off (`->withEvents(discover: false)` in `bootstrap/app.php`);
all listeners registered explicitly in `AppServiceProvider::registerListeners()`. Two
listeners that had *only* ever been discovered — `IssueCertificateOnCompletion` and
`RecordCourseGradeSnapshot` — were registered explicitly at the same time, since turning
discovery off would otherwise have silently stopped certificate issuance.

One registration mechanism, visible in one file, beats the convenience of discovery.

### H-2 — No route-permission map existed (FIXED)

There was no way to answer "what guards this endpoint?" other than reading
`routes/web.php` and hoping.

**Fix:** `App\Support\Security\RouteGuardAudit` plus `php artisan audit:routes`. A route
counts as guarded by middleware (`auth`, `permission:`, `role:`, `can:`, `signed`), by an
`authorize()`/`Gate::` call or a `FormRequest::authorize()` in its action, or by an
explicit entry in `PUBLIC_BY_DESIGN` with a stated justification.

**Result: 143 mutating routes, 0 unguarded.** The command exits non-zero on any finding,
so it works as a CI gate.

Four routes needed an explicit justification rather than a guard, because each is
protected by something a controller-body scan cannot see:

| Route | What actually guards it |
|---|---|
| `POST /register` | Open by design. Can only create an unverified account with the student role; no input selects a role. |
| `POST /invitations/{invitation}/accept` | A single-use token compared against its stored hash in `InvitationService::resolve`. |
| `PUT /storage/{path}` | Framework endpoint; `ReceiveFile` requires a valid signed relative URL before writing. |
| `POST /webhooks/payments/{method}` | Per-driver HMAC signature over the raw body. |

**Test:** `HardeningTest::test_no_mutating_route_is_unguarded_anywhere_in_the_app`.

### H-3 — Five mutating endpoints had no rate limit (FIXED)

| Endpoint | Limit | Why it matters |
|---|---|---|
| `POST /register` | 10/min | Bulk account creation |
| `POST /invitations/{invitation}/accept` | 10/min | **The token is the only guard — this is what stops it being brute-forced** |
| `POST /login` | 20/min | Backstop under the finer per-email+IP throttle |
| `POST /forgot-password`, `POST /reset-password` | 6/min | Reset-mail flooding |
| `POST /checkout` | 20/min | Opens orders and calls a gateway |
| `POST /editor/upload` | 60/min | Cheapest way to fill a disk or Cloudinary quota |

**Test:** `HardeningTest::test_sensitive_public_endpoints_are_throttled`.

### H-4 — No security response headers (FIXED)

**Fix:** `App\Http\Middleware\SecurityHeaders` on the `web` group —
`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, a restrictive `Permissions-Policy`,
`X-Permitted-Cross-Domain-Policies: none`, and HSTS **over HTTPS only**.

HSTS is conditional on purpose: it is meaningless on a plaintext response, and emitting
it from a local `http://` dev server pins the developer's browser to HTTPS for localhost,
which is genuinely painful to undo.

Existing headers are never overwritten — the framework's file-serving responses ship a
stricter CSP and sandbox of their own.

**Test:** `HardeningTest::test_security_headers_are_present_on_web_responses`,
`::test_hsts_is_not_sent_over_plain_http`.

### H-5 — Nothing prevented `APP_DEBUG=true` in production (FIXED) — *high*

A debug error page prints the full environment: database password, mail credentials,
`APP_KEY`. This is the highest-severity misconfiguration available in this stack, and the
usual defence — remembering to set it — is exactly the one that fails on a rushed deploy.

**Fix:** `AppServiceProvider::guardProductionConfig()` throws at boot when
`APP_ENV=production` and `APP_DEBUG=true`. The app fails loudly instead of leaking.

`.env.example` deliberately still ships `APP_DEBUG=true` — it is the *local* template and
a developer needs the error page. `.env.production.example` was added with safe defaults
(`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, blank `APP_KEY`).

**Test:** `HardeningTest::test_the_app_refuses_to_boot_in_production_with_debug_on`.

### H-6 — 14 dependency advisories across 3 packages (FIXED) — *high*

`composer audit` reported 14 advisories: `guzzlehttp/guzzle` (1 high, 8 medium),
`phpoffice/phpspreadsheet` (3 high), `guzzlehttp/psr7` (2 medium) — all transitive.

**Fix:** `composer update guzzlehttp/guzzle guzzlehttp/psr7 phpoffice/phpspreadsheet
--with-all-dependencies`. `composer audit` now reports **no advisories**. The
phpspreadsheet bump drives the Excel exports, so the reporting suite was re-run
specifically and passes.

### H-7 — Mass-assignment review of models added since v2 (FIXED — verified)

`Programme`, `ProgrammePart`, `Cart`, `CartItem`, `Order`, `OrderItem`, `Coupon`,
`PaymentMethod` and `Setting` all declare an explicit `$fillable` allow-list. None uses
`$guarded = []`.

`Order` has `status` and `paid_at` in `$fillable`, which is correct — `OrderFulfilmentService`
needs to write them — and safe, because no controller passes request input into
`Order::create()`. Verified adversarially rather than by reading: posting
`status=paid&paid_at=…` to checkout cannot produce a paid order that did not go through
fulfilment.

**Test:** `HardeningTest::test_models_added_since_v2_declare_an_explicit_fillable_allow_list`,
`::test_an_order_cannot_be_mass_assigned_to_paid_from_request_input`.

### H-8 — Upload re-validation (FIXED — strengthened)

All uploads already funnelled through `ValidatesMediaUploads`, a single choke point.
Section 15 added administrator-set ceilings (Settings → Uploads) applied **on top** of
each purpose's own policy, in `MediaPurpose::maxKb()` / `::allowedMimes()`.

The composition is **intersection, never union**: an administrator can narrow what the
institution accepts but can never widen what a purpose deliberately excludes.

Which purposes each ceiling governs is listed explicitly in `config/media.php` rather
than inferred, because inference got the interesting cases wrong — `LessonMedia` has an
intentionally large, env-tunable ceiling for the exceptional self-hosted video, and
`BrandAssets` needs SVG and ICO, which have no business in a general image allow-list.
Both are left ungoverned on purpose, as is `Signatures` (already stricter).

> **A defect this introduced, and its fix.** The initial global document allow-list was
> narrower than the purposes it governed, and the intersection silently revoked a
> deliberate Section 6 decision: submissions accept images so a photographed hand-in
> previews inline in the grading workspace. The suite caught it. The global list is now
> the **union** of what governed purposes legitimately accept, which is what makes
> intersection safe.

### H-9 — `.env` hygiene (FIXED)

`.env.example` documents every required key and contains no real credential (no populated
`APP_KEY`, no `sk_live_`/`pk_live_`). Gateway credentials are deliberately **not** env
values at all — they live encrypted in `payment_methods`, editable by an admin, rotatable
without a deploy, and every rotation is audited without its value.

**Test:** `HardeningTest::test_env_example_documents_required_keys_without_leaking_secrets`.

### H-10 — Queue worker and scheduler were undocumented (FIXED)

Without a queue worker, **no notification is ever sent and no certificate PDF is ever
rendered** — and nothing on any screen says so. Without the scheduler, digests never send
and audit retention never prunes.

**Fix:** `docs/production.md` — supervisor unit, cron entry, `queue:restart` on deploy,
what to monitor, and a first-run checklist that includes *not* running `DatabaseSeeder`
in production (it creates demo accounts whose password is `password`).

### H-11 — Section 12 money invariants re-confirmed (VERIFIED)

The brief asked for these to be **re-confirmed, not assumed**, after Sections 13 and 14
touched checkout and the curriculum. Each was tested against a real attempt to violate it.

| Invariant | How it was confirmed |
|---|---|
| Money re-resolved server-side | Tampered the stored cart line to ₦1.00 *and* posted `total`, `subtotal`, `discount_total`. Order was still written at the true ₦52,000 from `PricingService`, discount ₦0. |
| Webhook signature checked | Posted a forged `charge.success` body naming a real order, with no valid signature. Rejected 403; order stayed `pending`. |
| Fulfilment idempotent | Called `markPaid()` three times. First returned `true`, the rest `false`; exactly **one** enrolment resulted. |

**Test:** `HardeningTest`, the "Section 12 money invariants" block.

### H-12 — ACCEPTED: no Content-Security-Policy — *medium*

**Not fixed. Accepted, with the conditions for revisiting stated.**

A meaningful CSP here would have to permit TinyMCE's inline styles, Alpine's inline event
expressions, Bunny Fonts and the Cloudinary image host. A policy loose enough for all of
that (`'unsafe-inline'` on both script and style) provides very little real protection
while being easy to get subtly wrong — and a CSP that breaks the rich-text editor in
production is worse than none.

The XSS exposure it would mitigate is already addressed at the source: **every** rich-text
field is server-side sanitized through the `RichHtml` cast on save (mews/purifier
allow-list), and the highest-risk user-facing fields — forum and messaging — have tests
asserting that `<script>` and `style` are stripped.

**Revisit when** Alpine's inline expressions are replaced with CSP-friendly bindings, or a
nonce-based policy is introduced at the reverse proxy. `docs/production.md` notes the
proxy is the better place for it.

### H-13 — ACCEPTED: the private disk is served over HTTP — *low, verified safe*

Both the `local` and `private` disks set `serve => true`, which registers
`GET|PUT /storage/{path}` over `storage/app/private` — where assignment submissions and
certificate PDFs live.

Read the framework source rather than assuming: `ServeFile::hasValidSignature()` requires
a valid signed relative URL whenever the disk's visibility is not `public`, and the
`private` disk's visibility is `private`. `ReceiveFile` requires a signature
unconditionally. Both therefore refuse unsigned access.

**Accepted** because the guard is real and is the framework's own. `docs/production.md`
notes that nginx must not be given a static rule over `storage/app/private`, which would
bypass it.

---

## Performance

### H-14 — Missing composite indexes on the curriculum ordering path (FIXED)

Section 14 gave lessons, assessments and assignments a shared position ladder per bucket,
so the builder now reads all three on every outline render and every reorder — the shape
`where module_id = ? order by position`.

Separate indexes on `module_id` and `position` cannot serve that: MySQL filters on one and
then sorts. Composite indexes in (filter, sort) order serve both and remove the sort.

Added: `lessons(module_id, position)`, `assessments(module_id, position)`,
`assessments(course_id, position)`, `assignments(module_id, position)`,
`assignments(course_id, position)`, `modules(course_id, position)` — the last because only
a bare `position` index existed, which is useless for "this course's modules, in order".

### H-15 — Commerce catalogue filtered on an unindexed column (FIXED)

`Course::inCatalogue()` filters `status` **and** `visibility` together and is the busiest
guest-facing query in the app (homepage, catalogue, every programme page). Only `status`
was indexed, so visibility was a scan of the matches. Added `courses(status, visibility)`.

### H-16 — Audit trail lacked indexes for its own viewer (FIXED)

activitylog ships indexes for `log_name`, `subject` and `causer`, but the viewer filters by
`event` and by date range. Added `activity_log(event)` and `activity_log(created_at)`.

### H-17 — N+1 on the admin people list (FIXED)

The list eager-loaded `roles` but every row renders `<x-ui.avatar>`, which resolves the
avatar through the polymorphic `media` relation — **one extra query per person**. Measured:
9 → 20 queries when the page went from 3 to 15 users.

**Fix:** `->with(['roles', 'media'])`. Growth is now flat.

### H-18 — N+1 on the public catalogue (FIXED)

`CatalogueController` correctly adds `withSum('lessons as total_duration', …)`, but the
card read it as `$course->total_duration ?? $course->totalDurationMinutes()`. `withSum`
returns **NULL** for a course with no lessons, so `??` read a legitimately-empty aggregate
as "not loaded" and fired a per-card `SUM` — an N+1 on precisely the courses that had
nothing to sum. Measured: 13 → 19 queries from 3 to 15 courses.

**Fix:** ask whether the aggregate was *selected* (`hasAttribute`), then trust it however
empty.

**Tests (H-17, H-18):** `QueryBudgetTest` loads the five heaviest pages at two data
volumes and asserts the query count does not grow with the row count — which is the actual
definition of an N+1, and stays honest as the pages change. The public homepage's cached
stats band, the instructor course list and the audit viewer were confirmed already clean.

---

## Accessibility

### H-19 — Keyboard walk (VERIFIED — no change needed)

Walked the player, assessment-taking, the grading workspace, the gradebook matrix,
checkout, and the Section 14 curriculum builder.

The **Alt+↑/↓ keyboard-move path from Section 14 still works** after this section's
changes — verified in `resources/js/course-builder.js:142-150`, where the handler is
still bound to the row handle and still calls `moveItem()`. Nothing in Section 15 touched
the builder's JS.

Every interactive element carries `.focus-ring` (or `.focus-ring-inverse` on crimson).
The skip link is present and reaches `#main-content`.

### H-20 — Contrast audit (VERIFIED — no change needed)

Computed rather than eyeballed, and asserted so a future palette tweak cannot drop a
pairing below AA. All eight body-text pairings meet 4.5:1, including **white on the
crimson marketing hero**. The `focus-ring-inverse` token meets the 3:1 non-text threshold
against crimson, which is the reason it exists — a crimson ring on a crimson hero is no
ring at all.

The audit also asserts that base `--uprl-gold` still *fails* on white, so the
`--uprl-gold-ink`-for-text rule cannot be "re-examined" by someone assuming it was
over-cautious.

**Test:** `ContrastTest`.

### H-21 — Reduced motion (VERIFIED — no change needed)

The global `@media (prefers-reduced-motion: reduce)` rule in `resources/css/app.css`
neutralises animation, transition and smooth scrolling app-wide. New Section 15 UI (the
settings tabs, the audit viewer's expandable rows) uses `<details>` and CSS transitions,
both covered by it.

### H-22 — The audit viewer's expandable rows (FIXED by design choice)

Expandable diff rows are a native `<details>/<summary>`, not an Alpine panel: keyboard
operable and screen-reader announced with no JavaScript, and they survive printing. The
diff is a real `<table>` with a `<caption>`, `scope="col"` headers and `scope="row"` field
names, so a screen reader can navigate it as a table rather than a wall of text.

---

## Correctness defects found while sweeping

Not security issues, but real bugs the sweep surfaced. All fixed.

### H-23 — `AuditActivity::context()` returned mangled data

`(array)` on a `Collection` **object** yields its protected-property keys
(`"\0*\0items"`), not its items. Element access works either way, which is why the diff
accessors were fine and this one was not. Fixed to use `->toArray()`.

### H-24 — The audit trait clobbered deliberately-authored entries

spatie's `ActivityLogger::log()` calls `tapActivity()` on the subject for **manually**
logged entries too, not just model events. The trait was rewriting their event and
description — turning "Signed in" into "User X was updated" and discarding the verb the
viewer filters on.

Fixed: redaction stays unconditional (it must never be skippable), but renaming now
applies only to genuine Eloquent events.

### H-25 — `SettingsRepository::set()` compared against a stale memo

The repository is a singleton with an in-request memo. During `migrate:fresh --seed` the
map is captured at boot and the table is then dropped and rebuilt beneath it, so `set()`
compared against stale state, concluded nothing had changed, and **wrote nothing at all** —
silently. Found because the seeded settings change never appeared. Fixed: a writer
resolves fresh before comparing.

### H-26 — A false-positive credential rotation was possible in principle

An encrypted column produces fresh ciphertext on every write, so dirtiness judged on
ciphertext would report a credential rotation every time a payment method was merely
renamed — a false alarm in the log a security review trusts most.

Verified empirically rather than trusting the framework docs: Laravel's
`originalIsEquivalent()` compares **decrypted** values for encrypted castables, so
re-saving identical credentials is correctly not a rotation. Locked in by
`AuditTrailTest::test_saving_a_payment_method_without_changing_the_secret_is_not_a_rotation`.

---

## Re-running this audit

```bash
php artisan audit:routes            # route → guard map; non-zero if anything is unguarded
php artisan audit:routes --csv=map.csv
composer audit                      # dependency advisories
php artisan test tests/Feature/Hardening   # every assertion in this report
```
