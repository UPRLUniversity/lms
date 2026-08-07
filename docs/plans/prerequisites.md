# Plan — Course prerequisites & part progression

**Status:** DECIDED, ready to build. See §0 for the settled answers.
**Written:** 2026-08-07, after investigating the current state at the human's request.
**Decisions taken:** 2026-08-07.

---

## 0. Settled decisions

| # | Question | Decision |
|---|---|---|
| 1 | Which model? | **Part-level progression.** Per-course prerequisites deferred to a later, separate section |
| 2 | Unlock bar? | **All compulsory courses passed, AND — where the part states one — its `credit_target` in earned credits.** Two bars, both shown (§3) |
| 3 | Waitlists? | **Refused.** A waitlist place you cannot use is a false promise, and promotion is automatic (§6.4) |
| 4 | Pass/fail? | **Yes — build it.** `grade_bands.is_pass`, backfilled as `grade_point > 0` (§6.1) |
| 5 | Gate Part I on the entry fee? | **NO.** It would deadlock the funnel — see §0.1. This one I am overruling |

### 0.1 Why Part I must not be gated on the entry fee

The proposal was reasonable on its face and I checked it before answering. It cannot
work, for a structural reason:

`PricingService::entryFeeLinesFor` charges a programme's registration and administration
fees **only when the cart already contains a paid course from that programme**. There is
no other way to pay them — no standalone "join CPR" purchase exists.

So gating Part I on "entry fee already paid" produces:

```
student cannot buy CPR112       ← because the entry fee is unpaid
entry fee is only charged        ← when buying a CPR course
                                 ⇒ nobody can ever start CPR
```

A hard deadlock closing the entire commercial funnel.

**The first part of a programme is therefore always open**, and the entry fee stays
enforced where it already is: in the cart, at checkout, at the moment money moves. That
is the correct place for it — it is a pricing rule, not a progression rule, and this plan
should not move it.

If UPRL ever wants a genuine "register for the programme first" step, that is a different
feature — a standalone programme-registration purchase — and it would need its own
design.

### 0.2 On choosing the unlock bar

The suggestion was "all compulsory courses". That is most of the answer, but on its own
it lets a student skip every *required elective* and still progress — and required
electives count toward the published total (`CourseRequirement::countsTowardTarget()`),
so that student arrives at the end of the programme short of credits, having been told
all along they were on track. Discovering that at graduation is the worst possible time.

Conversely, using `credit_target` alone is not enough either: it would let a student
reach 24 credits on electives while skipping a core paper, and it is simply unavailable
for parts that state no target — which is most of them (CPR Part II, and all three NPV
parts, are `null` in the seeder).

**So: both.** All compulsory passed is the primary bar and always applies; the credit
target is an additional bar that applies only where the part states one. It degrades
gracefully, it matches what a registrar actually enforces, and the UI can show both:

> ✅ 8 of 8 compulsory courses passed  ·  ⚠️ 21 of 24 credits

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
G.progression_rule = 'open'                     → always allowed
OR P is the first part of G (lowest position)   → always allowed  (§0.1)
OR EVERY earlier part of G is CLEARED
```

A part is **cleared** when both bars are met:

| Bar | Applies | Definition |
|---|---|---|
| Compulsory | always | every course placed in the part with `requirement = compulsory` has been **passed** |
| Credits | only when the part states a `credit_target` | sum of `credit_load` over the student's **passed** courses in that part ≥ `credit_target` |

**Passed** = an enrolment with status `Completed` **and** a `CourseGradeRecord` whose
grade band is a passing band (§6.1). An ungraded completion counts as passed — some
courses have no assessment, and refusing them would gate on data that will never arrive.

`programme_parts.unlock_credits` overrides `credit_target` for the credit bar, for the
case where the registrar wants a progression bar different from the published total.

See §0.2 for why both bars, rather than either alone.

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

### Phase 0 — pass/fail (its own section, merged first)

Progression asks "has this student *passed* the compulsory papers", and the system cannot
currently answer that (§6.1). So this ships first, on its own, and is useful on its own —
a gradebook that distinguishes pass from fail is worth having whether or not progression
ever lands.

0.1 Migration `add_is_pass_to_grade_bands` — `is_pass` boolean, **no default**, backfilled
    in the same migration as `grade_point > 0` for every existing band. No default,
    because every band must make a deliberate choice rather than inherit one.
0.2 `GradeBand::$fillable` + cast; `GradeScale` validation — a scale must have **at least
    one** passing band and at least one failing band, or it cannot express an outcome
0.3 Grade-scale editor — a "Pass" toggle per band row, with the boundary shown plainly
    ("Pass mark: 40%") derived from the lowest passing band
0.4 `CourseGradeRecord::isPass()` — reads the frozen `scale_snapshot`, so a historical
    record keeps the verdict it was awarded under even if the scale is later re-cut
0.5 Show pass/fail in the gradebook, the student's grade view and the certificate
    eligibility check
0.6 Tests: the backfill reproduces existing intent for both seeded scales; a snapshot
    keeps its verdict when the live scale changes; a scale with no failing band is refused

**`scale_snapshot` is the subtle part.** `CourseGradeRecord` already freezes the whole
scale at computation time precisely so a later edit never rewrites a recorded grade. The
pass verdict must be read from that snapshot, not from the live scale — otherwise
re-cutting a grade boundary in 2027 would retroactively fail students who graduated in
2026, and progression would start refusing people it had already let through.

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
   passedCoursesIn(User, ProgrammePart): Collection
   compulsoryOutstanding(User, ProgrammePart): Collection   // bar 1
   creditsEarnedIn(User, ProgrammePart): int                // bar 2
   creditBarFor(ProgrammePart): ?int                        // unlock_credits ?? credit_target
   isPartCleared(User, ProgrammePart): bool
   check(User, Course): ProgressionVerdict
   verdictsFor(User, Collection<Course>): Collection        // bulk, for the catalogue
   ```
   `verdictsFor` exists so the catalogue can render 40 cards without 40 round trips —
   the same reason `PricingService` batches.

4. **`App\Support\Courses\ProgressionVerdict`** — `allowed`, `blockingPart`,
   `outstandingCompulsory`, `creditsEarned`, `creditsRequired`, `message`. A value
   object, so one sentence serves the exception, the toast, the cart error and the
   catalogue badge, and they can never word it differently.

5. **Tests** for the service alone: a course in two programmes unlocked via either;
   unplaced courses; the first part; `open` programmes; a part with no `credit_target`
   (compulsory bar only); withdrawn and failed enrolments not counting; an ungraded
   completion counting.

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
12. **Waitlisting** — the gate runs before the full-course branch in `selfEnroll`, so a
    blocked student is refused rather than queued (§6.4)

### Phase 3 — the UI

13. **Course page** (`catalogue/_enrol.blade.php`) — a locked state before the buy path:
    > 🔒 **Complete CPR Part I first**
    > You have 12 of 24 credits. [See CPR Part I →]

    Locked, not hidden. A student must be able to see what they are working toward.
14. **Catalogue cards** — a small lock chip, fed by `verdictsFor`
15. **Programme page** — parts rendered locked/unlocked with a progress bar
16. **Cart** — refuse on add with the reason as a toast; if a rule changes while an item
    sits in the cart, flag the line rather than silently dropping it
17. **Admin enrol form** — when the student fails the check, an explicit
    "Enrol anyway (records an override)" with a required reason
18. **Bulk import preview** — a `prerequisite_not_met` row problem, imported anyway,
    consistent with the Section 17 preview vocabulary

### Phase 4 — admin control

19. Programme edit form — a `progression_rule` selector with plain-language help
20. Programme part form — optional `unlock_credits` override
21. **Backfill command** `php artisan progression:audit` — lists students currently
    enrolled in a course they would now be blocked from, so switching a programme to
    `sequential` is an informed decision rather than a surprise

### Phase 5 (optional, separate section) — per-course prerequisites

22. `course_prerequisites` table: `course_id`, `required_course_id`, unique pair
23. Cycle detection on save (A requires B requires A must be refused at authoring time)
24. An additional gate inside `ProgressionService::check`, needing no change to any
    caller

---

## 6. Problems that need deciding, not just coding

### 6.1 Pass/fail does not exist yet — build it first

`GradeBand` has a label, grade point and percentage range, but **nothing marks a band as
passing**. So "must PASS CPR112" is not expressible today; only "must COMPLETE" it is.

UPRL has confirmed a genuine pass bar is wanted, so this becomes **Phase 0** — a
prerequisite of the prerequisite work, shipped and merged before progression is built on
top of it.

**The backfill is unambiguous.** Both seeded scales put the fail band, and only the fail
band, at `grade_point = 0.00`:

| Scale | Bands | Fail |
|---|---|---|
| 5-point | A 5.0 · B 4.0 · C 3.0 · D 2.0 · E 1.0 · F 0.0 | F |
| 4-point | A 4.0 · B 3.0 · C 2.0 · D 1.0 · F 0.0 | F |

So `is_pass = grade_point > 0` reproduces the existing intent exactly, for every scale in
the system. It is a derivation of current data, not a guess about it.

Making it an explicit **column** rather than leaving it as `grade_point > 0` computed at
read time matters: a scale could legitimately award a non-zero point to a failing band
(some institutions give 0.5 for a near-miss), and a progression rule silently treating
that as a pass would be very hard to spot. The column lets the registrar say so.

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

### 6.4 Waitlists — refused

**Decided: the gate applies to waitlisting.**

Promotion off the waitlist is automatic (`EnrollmentService::promote`, triggered when a
seat frees). Allowing a blocked student onto the waitlist would therefore mean the system
eventually enrols them *past the gate*, with no human in the loop — the rule would leak
through the one path nobody is watching.

Re-checking at promotion instead was the alternative, and it is worse: the student holds
a queue position for weeks, gets skipped when their turn arrives, and the seat has to
cascade to the next person. A place you cannot use is a false promise, and refusing up
front is both honest and simpler to reason about.

### 6.5 Refunds

If a student buys a Part II course through an override and it is later reversed, nothing
recalculates. Out of scope — noting it so it is not discovered as a surprise.

---

## 7. Decisions — all settled

See §0. In summary: part-level progression; two bars (all compulsory passed, plus the
credit target where stated); waitlists refused; pass/fail built first as Phase 0; the
first part of a programme always open.

Nothing is outstanding. The one item deliberately deferred is per-course prerequisites
(Phase 5), which needs curriculum decisions rather than code.

---

## 8. Sizing and sequencing

| Phase | Scope | Weight | Ships as |
|---|---|---|---|
| 0 — pass/fail on grade bands | useful alone | small-medium | **its own section, merged first** |
| 1 — rule + service + tests | no behaviour change | small | Section N |
| 2 — enforcement at 7 call sites | the risky part | **medium-large** | Section N |
| 3 — UI in 6 places | mostly presentation | medium | Section N |
| 4 — admin control + audit command | small | small | Section N |
| 5 — per-course prerequisites | needs curriculum input | medium | later, separate |

**Two sections, in order.** Phase 0 first and alone, because progression is built on top
of a pass verdict that does not exist yet, and because a gradebook that knows pass from
fail is worth having on its own merits. Phases 1–4 then form the progression section.

The risk is concentrated entirely in Phase 2: seven call sites, **three of which must
deliberately NOT enforce**. The deliverable that makes it safe is a test per call site —
including the three that allow, because those are the ones a future change would silently
break.

### The order that matters most

Build **Phase 1 with no enforcement at all** and merge it. A service that can answer "is
this student allowed?" — with the audit command from step 21 able to report what *would*
be blocked — lets the registrar look at real numbers against real students before a
single door is closed. Switching a programme to `sequential` is then a decision made with
evidence, rather than a change nobody can predict the blast radius of.

The risk is concentrated entirely in Phase 2: six call sites, three of which must
*deliberately not* enforce. A test per call site asserting the intended behaviour —
including the three that allow — is the deliverable that makes this safe.
