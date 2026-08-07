# Plan — Course prerequisites & part progression

**Status:** proposed, not started. Awaiting three decisions (§7).
**Written:** 2026-08-07, after investigating the current state at the human's request.

---

## 1. What exists today

**Nothing gates progression.** The only occurrence of the word "prerequisite" outside a
security header is prose in `ProgrammeSeeder` describing short courses as having none.
A student can enrol straight into a Part III course today, with no Part I behind them.

What *does* exist is the structure to build on:

| Piece | Where | State |
|---|---|---|
| Ordered parts within a programme | `programme_parts.position` | ✅ Part I → II → III |
| Course ↔ part placement | `course_programme_part` | ✅ many-to-many, with `credit_load` + `requirement` |
| Published credit bar per part | `programme_parts.credit_target` | ✅ populated (CPR Part I = 24) |
| Credits a student has earned | `ProgrammePart::creditsCounted()` | ✅ exists, but computes the PART's credits, not a student's |
| Completion of a course | `EnrollmentStatus::Completed` | ✅ |
| Pass / fail of a course | — | ❌ **does not exist** (see §6.1) |
| Student ↔ programme membership | — | ❌ **implicit only** (see §6.2) |

`EnrollmentService::selfEnroll` checks published → mode → window → paywall →
duplicate → capacity, and stops. There is no sixth check, and no place currently
designed to hold one.

### 1.1 The finding that shapes everything

There are **four** ways a student becomes enrolled, and only one of them goes through
`selfEnroll`:

```
selfEnroll ────────────────────────────► free course, student clicks "Enrol"
adminEnroll ← AdminEnrollmentController ► staff adds a student directly
adminEnroll ← OrderFulfilmentService ───► A PAID COURSE WAS BOUGHT
adminEnroll ← BulkEnrollmentService ────► CSV import
```

`adminEnroll` **deliberately skips the paywall check** — that is how a purchase reaches
the course. So a prerequisite rule added only to `selfEnroll` would be bypassed by the
simplest possible action: *paying for a Part III course*. Every UPRL programme course is
paid.

This single fact is why §4 spends most of its length on *where* the gate goes rather than
*what* the rule is.

---

## 2. The choice to make

Two designs, genuinely different:

### Option A — Part-level progression (recommended)

> "You must have earned 24 credits in CPR Part I before you may enrol in any CPR Part II
> course."

- Matches the prospectus, which is organised by part, and matches the stated need
  ("students should take Part I before Part II or Part III").
- **Needs no new data entry.** The placements, credit loads and credit targets are
  already in the database. Turning the rule on is a per-programme switch.
- Coarse: cannot express "CPR216 specifically needs CPR112".

### Option B — Per-course prerequisites

> "CPR216 requires CPR112."

- Precise, and what most universities mean by "prerequisite".
- Requires an admin to author a prerequisite graph by hand — roughly 50 relationships
  for the current catalogue — and the published prospectus does not appear to state
  them, so somebody has to *decide* them first.
- Brings graph problems: cycles, transitive chains, what happens when a prerequisite
  course is archived.

### Recommendation: **A first, designed so B slots in later**

Option A answers the actual requirement, needs no data authoring, and can ship in one
section. Build the evaluation behind a single `ProgressionService` with a list of gates,
so Option B becomes an additional gate rather than a rewrite.

**Do not build both at once.** B's real cost is not code, it is the curriculum decisions
somebody has to make before the table can be filled in.

---

## 3. The rule, precisely

A student may enrol in a course placed in **part P** of programme **G** when:

```
G.progression_rule = 'open'                          → always allowed
OR P is the first part of G (position = lowest)      → always allowed
OR student's earned credits in EVERY earlier part of G ≥ that part's unlock bar
```

**Earned credits in part P** = sum of `credit_load` over that student's
`Completed` enrolments on courses placed in P.

**A part's unlock bar** = `programme_parts.unlock_credits` when set, else
`credit_target`, else "all compulsory courses in the part completed".

Rationale for using `credit_target`: it is already the authoritative published figure,
and it is deliberately *lower* than the sum of listed credits (CPR Part I prints 24
against 28 listed) precisely because pure electives are optional. That gap is the
student's choice, and the bar should respect it rather than demand everything.

### 3.1 Courses in more than one programme

A course can sit in several parts (CPR 112 is in both CPR Part I and Professional
Variant Part 1). **The gate passes if ANY of its placements is unlocked.** A student
should never be blocked from a course they are legitimately entitled to via a different
programme.

### 3.2 Unplaced courses

A course in no programme part is never gated. Short courses and Master Classes are
deliberately standalone.

---

## 4. Where the gate goes — the hard part

| Entry point | Behaviour | Why |
|---|---|---|
| `selfEnroll` | **Refuse** | The rule's home, mirroring the paywall |
| **Add to cart** | **Refuse** | Must refuse *before* money moves |
| **Checkout** | **Re-check, refuse** | The cart may be minutes old; re-validate like the paywall |
| `adminEnroll` ← OrderFulfilmentService | **Allow** | Money has already changed hands. Refusing here strands a paid order — the failure the cart check exists to prevent |
| `adminEnroll` ← AdminEnrollmentController | **Allow, with recorded override** | Staff must be able to admit an exception (transfer student, prior credit). It must leave a trace |
| `adminEnroll` ← BulkEnrollmentService | **Flag in preview, import anyway** | A bulk import is a staff action; the preview names the rows that skip a prerequisite so it is a decision, not an accident |

The asymmetry is the design. **The gate belongs where the student chooses, not where the
system fulfils.** Anything that has already been paid for must complete.

### 4.1 The cart is the real paywall for this feature

Because every programme course is paid, **the cart check is the primary enforcement
point**, not `selfEnroll`. `selfEnroll` matters only for free courses. Getting this
backwards would ship a feature that never fires.

---

## 5. Implementation

### Phase 1 — the rule (no behaviour change)

1. **Migration** `add_progression_rule_to_programmes`
   - `programmes.progression_rule` enum `open|sequential`, **default `open`**
   - `programme_parts.unlock_credits` unsigned int nullable

   Defaulting to `open` means merging this changes nothing until a human switches a
   programme on. That is deliberate: the migration must never silently lock out live
   students.

2. **`App\Enums\ProgressionRule`** — `Open`, `Sequential`, with `label()`.

3. **`App\Services\Courses\ProgressionService`**
   ```php
   creditsEarnedIn(User, ProgrammePart): int
   unlockBarFor(ProgrammePart): int
   isPartUnlocked(User, ProgrammePart): bool
   check(User, Course): ProgressionVerdict   // allowed + reason + progress numbers
   coursesBlockedFor(User, Collection<Course>): Collection  // bulk, for the catalogue
   ```
   `coursesBlockedFor` exists so the catalogue can render 40 cards without 40 queries.

4. **`App\Support\Courses\ProgressionVerdict`** — `allowed`, `partName`, `earned`,
   `required`, `message`. A value object, so the same sentence appears in the exception,
   the toast, the cart error and the catalogue badge.

5. **Tests** for the service alone: multi-programme courses, unplaced courses, first
   part, `open` programmes, a part with no target, credits from withdrawn enrolments not
   counting.

### Phase 2 — enforcement

6. `EnrollmentException::prerequisiteNotMet(ProgressionVerdict)`
7. `EnrollmentService::selfEnroll` — the sixth check, directly after the paywall
8. `CartController::store` / `CartService` — refuse with the verdict's message
9. `CheckoutService` — re-check every cart line; refuse the whole checkout naming the
   offending course
10. `adminEnroll` — new `bool $overridePrerequisites = false` parameter, default
    **false**, passed `true` by `AdminEnrollmentController`, `OrderFulfilmentService`
    and `BulkEnrollmentService`. Default-false so a *new* caller added later inherits
    the safe behaviour.
11. Migration `add_prerequisite_override_to_enrollments` — `prerequisite_override_by`,
    `prerequisite_override_reason`, so an exception is auditable

### Phase 3 — the UI

12. **Course page** (`catalogue/_enrol.blade.php`) — a locked state before the buy path:
    > 🔒 **Complete CPR Part I first**
    > You have 12 of 24 credits. [See CPR Part I →]

    Locked, not hidden. A student must be able to see what they are working toward.
13. **Catalogue cards** — a small lock chip, fed by `coursesBlockedFor`
14. **Programme page** — parts rendered locked/unlocked with a progress bar
15. **Cart** — refuse on add with the reason as a toast; if a rule changes while an item
    sits in the cart, flag the line rather than silently dropping it
16. **Admin enrol form** — when the student fails the check, an explicit
    "Enrol anyway (records an override)" with a required reason
17. **Bulk import preview** — a `prerequisite_not_met` row problem, imported anyway,
    consistent with the Section 17 preview vocabulary

### Phase 4 — admin control

18. Programme edit form — a `progression_rule` selector with plain-language help
19. Programme part form — optional `unlock_credits` override
20. **Backfill command** `php artisan progression:audit` — lists students currently
    enrolled in a course they would now be blocked from, so switching a programme to
    `sequential` is an informed decision rather than a surprise

### Phase 5 (optional, separate section) — per-course prerequisites

21. `course_prerequisites` table: `course_id`, `required_course_id`, unique pair
22. Cycle detection on save (A requires B requires A must be refused at authoring time)
23. An additional gate inside `ProgressionService::check`, needing no change to any
    caller

---

## 6. Problems that need deciding, not just coding

### 6.1 There is no pass/fail concept

`GradeBand` has a label, grade point and percentage range — but **nothing marks a band
as passing**. So "must PASS CPR112" is not expressible today; only "must COMPLETE
CPR112" is.

Today a student who completes a course with the lowest possible grade counts as having
earned its credits. If UPRL needs a genuine pass bar, that is a **prerequisite of this
prerequisite work**: add `grade_bands.is_pass`, backfill it, and only then can the rule
say "earned" rather than "completed".

**Recommendation:** ship on `Completed` first. Add `is_pass` as its own small piece of
work if the registrar confirms it is needed. Do not conflate them.

### 6.2 "Which programme is this student in?" has no stored answer

There is no `programme_user` table. Membership is implied by two different signals —
having paid a programme's entry fee (`order_items.programme_id`), and being enrolled in
courses placed in its parts.

The rule in §3 sidesteps this by evaluating **per course, per placement** rather than
per student-programme. That is deliberate and I believe correct: it needs no new
membership concept, and it degrades gracefully for a student taking one paper out of
interest.

Flagging it because if UPRL later wants "you are a CPR student, here is your path",
that *does* need an explicit membership record, and it would change this design.

### 6.3 Grandfathering

Switching a programme to `sequential` must never revoke access a student already has.
The gate applies at **enrolment time only** — an existing `Active` enrolment is never
re-evaluated. `progression:audit` (step 20) exists to make the consequences visible
before the switch, not to enforce anything retroactively.

### 6.4 Waitlists

A student may currently join a waitlist for a course they cannot yet enrol in. The gate
should apply to waitlisting too — otherwise they are promoted into a course they are not
entitled to. **Decide:** refuse the waitlist, or allow it and re-check at promotion?
I lean toward refusing, because a waitlist place they cannot use is a false promise.

### 6.5 Refunds

If a student buys a Part II course through an override and it is later reversed, nothing
recalculates. Out of scope — noting it so it is not discovered as a surprise.

---

## 7. Three decisions needed before starting

1. **Option A or B?** (§2) — my recommendation is A, with B as a later, separate section.
2. **What is the unlock bar?** (§3) — `credit_target` (recommended), all-compulsory, or
   all-courses.
3. **Waitlists** (§6.4) — refuse, or allow and re-check at promotion?

Two more worth answering, though they need not block a start:

4. Does UPRL need a real **pass/fail** bar (§6.1), or is completion sufficient?
5. Should **Part I itself** ever be gated — e.g. by having paid the programme entry
   fee — or is the first part always open?

---

## 8. Sizing

| Phase | Scope | Rough weight |
|---|---|---|
| 1 — rule + service + tests | no behaviour change | small |
| 2 — enforcement at 6 call sites | the risky part | **medium-large** |
| 3 — UI in 6 places | mostly presentation | medium |
| 4 — admin control + audit command | small | small |
| 5 — per-course prerequisites | separate section | medium |

Phases 1–4 are one section. Phase 5 is its own.

The risk is concentrated entirely in Phase 2: six call sites, three of which must
*deliberately not* enforce. A test per call site asserting the intended behaviour —
including the three that allow — is the deliverable that makes this safe.
