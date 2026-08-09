# Plan — Academic home: giving a person a faculty, a department and a programme

**Status:** DESIGNED, nothing built.
**Written:** 2026-08-09, after the human asked whether students should have a faculty and
department, and whether it should affect how they use the system.

> **Starting from a cold session? Read this box first.**
>
> Everything needed is in this file. No prior conversation is required.
>
> | Section | Branch | State |
> |---|---|---|
> | 21 — the record | `section/21-academic-home` | ⬜ not started |
> | 22 — the student's path | `section/22-my-programme` | ⬜ not started, needs 21 |
> | 23 — staff scope & targeting | `section/23-department-scope` | ⬜ not started, needs 21 |
> | 24 — award documents | `section/24-award-lines` | ⬜ not started, needs 21 |
>
> **Section 21 must land first and alone.** 22, 23 and 24 are independent of each other
> and can be built in any order, or dropped, once 21 is merged.
>
> **The answer to the original question is not the obvious one.** A department dropdown on
> the student profile is the wrong shape and §2 explains why. The record actually missing
> is *which qualification the student is reading for*; faculty and department fall out of
> it. Read §2 before writing any code — starting from the obvious design produces two
> sources of truth for "what department is this student in", and they will drift.
>
> **Nothing here gates access to anything.** Buying and enrolling stay exactly as open as
> they are today (§5.2 — this one was considered and refused, with reasons).
>
> This plan does **not** change `ProgressionService` (Section 19). §7.1 explains why the
> note in `docs/plans/prerequisites.md` §6.2 — "an explicit membership record would change
> this design" — turns out not to apply.
>
> Follow CLAUDE.md as always: branch from a fresh `main`, one section at a time,
> `php artisan test` green before reporting, then STOP.

---

## 0. Settled decisions

| # | Question | Decision |
|---|---|---|
| 1 | Do students get a faculty/department? | **Yes, but derived from their programme, never typed in** (§2, §3.2) |
| 2 | Where does the department live? | **One `users.department_id` column, one writer per role** — authored for staff, written by the service for students (§3.2) |
| 3 | How is programme membership stored? | **`programme_user` table**, many memberships, exactly one primary (§3.1) |
| 4 | How does a student acquire one? | **The entry fee already answers this.** Fulfilment creates it; no new student-facing step (§4) |
| 5 | Does it restrict which courses a student may buy or enrol in? | **No. Refused** (§5.2) |
| 6 | Do programmes get a department? | **Yes** — `programmes.department_id`, nullable (§3.3) |
| 7 | Students with no programme at all? | **First-class, not an error state.** Everything degrades to "Unaffiliated" (§7.2) |
| 8 | Does this change Section 19 progression? | **No** (§7.1) |
| 9 | Backfill for existing students? | **Reported first, written second**, mirroring `progression:audit` (§4.3) |

---

## 1. What exists today

The academic hierarchy is real and already load-bearing — for **courses**:

| Piece | Where | State |
|---|---|---|
| Faculty → Department | `faculties`, `departments` | ✅ seeded: 2 faculties, 6 departments |
| Course → Department | `courses.department_id` (nullable) | ✅ set for every seeded course |
| Catalogue filters | `CatalogueController` + `catalogue/index.blade.php` | ✅ faculty and department, dependent selects |
| Report filters | `LearnerReport`, `InstructorReport`, `ComplianceReport` | ✅ all filter by the **course's** department |
| Admin CRUD | `Admin\FacultyController`, `Admin\DepartmentController` | ✅ |
| Programme → Department | — | ❌ does not exist |
| **User → Department** | — | ❌ **does not exist** |
| **User → Programme** | — | ❌ **does not exist** |

Two consequences are already visible in the code, both written down at the time as
deliberate compromises rather than oversights:

**`ComplianceReport` runs on a documented proxy.** Its own docblock says it: *"users have
no direct department, so a department cohort = everyone enrolled in that department's
courses"*. That is recorded in `docs/decisions.md` (Section 10, item 3). It is a
reasonable proxy and it is also wrong at the edges — a Public Relations student who takes
one Leadership elective appears in the Leadership cohort, and a student who has enrolled
in nothing appears in no cohort at all, which is precisely the person a compliance report
is trying to find.

**Progression sidesteps membership on purpose.** `docs/plans/prerequisites.md` §6.2:
*"There is no `programme_user` table. Membership is implied by two different signals —
having paid a programme's entry fee (`order_items.programme_id`), and being enrolled in
courses placed in its parts."* Section 19 evaluates per course, per placement, precisely
so it never has to ask the unanswerable question.

So the gap is not hypothetical, it has been worked around twice.

---

## 2. The question, re-framed

The human asked for faculty and department **on the student**. The instinct is right and
the obvious implementation is wrong. Two reasons, and the second is the one that matters.

### 2.1 A department alone cannot answer the questions people actually ask

"Which department is this student in?" is rarely the real question. The real questions are
*"what is this student reading for, how far through it are they, and what is left?"* A
department cannot answer any of those. A programme answers all three, and a department
comes free with it, because a programme is owned by one.

UPRL's structure makes this concrete. Departments teach — Public Relations, Journalism &
Media, Strategic Communication. Programmes qualify — CPR, DPR, the Professional Variant,
the Master Class. A student does not register with a department; they register for CPR.
The `programmes` migration already says this in its own header comment: departments answer
*who owns and teaches this course*, programmes answer *how it is packaged, examined and
charged for*.

### 2.2 A typed-in department creates a second source of truth

Put a department dropdown on the student profile and the system immediately holds two
answers to the same question: the one an admin typed, and the one implied by the CPR the
student is actually paying for and enrolled in. Nothing keeps them equal. They will
disagree, the disagreement will surface in a report, and there will be no principled way
to say which one is right.

The codebase has already settled this class of problem twice, and the same answer applies
here: **one writer per fact.** `CurriculumOrderService` owns curriculum positions;
assessment placement is derived and never authored. Academic home should be derived too.

### 2.3 So: membership is the record, department is a projection of it

```
student ──buys a CPR course──► entry fee charged ──► programme_user row (CPR, primary)
                                                            │
                                     CPR is owned by ────────┤
                                                            ▼
                                        Department of Public Relations
                                                            │
                                                            ▼
                                   Faculty of Communication & Media Studies
```

Staff are the exception, and they are the reason the department column exists at all: an
instructor or a head of department is never enrolled in a programme, so for them the
department genuinely is the primary fact and has to be typed in. §3.2 gives both cases one
column and keeps one writer for each.

---

## 3. The model

### 3.1 `programme_user` — the membership

```
programme_user
  user_id        FK users, cascade
  programme_id   FK programmes, cascade
  status         enum: active | completed | withdrawn
  is_primary     bool
  started_at     timestamp
  completed_at   timestamp nullable
  source         enum: purchase | admin | import | backfill
  unique (user_id, programme_id)
```

**A table, not a column on `users`.** A student can hold a CPR and later a DPR; the Master
Class is deliberately standalone and someone may sit it alongside anything. The entry fee
is already charged once *per programme*, which means the commercial model has assumed
multiple memberships from the start.

**Exactly one primary, enforced in the service, not the schema.** A partial unique index on
`(user_id) where is_primary` is not portable across the three drivers this app supports.
`AcademicHomeService::setPrimary()` clears the others in the same transaction, and a test
asserts a second primary cannot be created through any public path.

**`source` matters for the backfill.** A row written by `backfill` is a guess made from
evidence; a row written by `purchase` is money. §4.3 uses the distinction to make the
backfill reversible.

### 3.2 `users.department_id` — one column, one writer per role

```php
$table->foreignId('department_id')->nullable()->after('title')->constrained()->nullOnDelete();
```

| Role | Who writes it | Where |
|---|---|---|
| instructor, admin, super-admin, auditor | **an admin types it** | the user create/edit form |
| student | **`AcademicHomeService`** | whenever the primary membership changes |

The student's field renders read-only in the admin form, showing its provenance:

> Department of Public Relations — from **CPR (Certificate in Public Relations)**.
> Change the programme to change this.

It is denormalised on purpose. Every report that wants "students in the Faculty of
Communication" becomes one join instead of three, and `DashboardService` already sweeps
whole tables for its cached aggregates. The cost of denormalising is drift, and the answer
to drift is the same one the repo already uses for curriculum order: a single writer, plus
a command that reports disagreement rather than trusting that it cannot happen —
`php artisan academic-home:audit` lists every student whose stored department differs from
the one their primary programme implies, and `--fix` re-derives them.

**Faculty is never stored.** It is `department.faculty`, one hop, and a stored copy would
be a third thing to keep in step for no gain.

### 3.3 `programmes.department_id`

Nullable, `nullOnDelete`. A programme is owned by the department that teaches it — CPR and
DPR by Public Relations, the Master Class arguably by nobody, which is why it is nullable
rather than required. A programme with no department yields a student with no department,
and §7.2 makes that a supported state rather than a bug.

### 3.4 What `User` gains

```php
programmes(): BelongsToMany          // withPivot(status, is_primary, started_at, source)
primaryProgramme(): ?Programme       // the is_primary row, null for most people
department(): BelongsTo              // the stored column
faculty(): ?Faculty                  // $this->department?->faculty
academicHome(): AcademicHome         // value object: faculty, department, programme, label
```

`AcademicHome` is a value object for the same reason `ProgressionVerdict` is one: the
student dashboard, the admin user table, the roster, the report row and the certificate
all render the same phrase, and a value object is what stops five call sites inventing
five slightly different wordings. Its `label()` returns `"CPR · Department of Public
Relations"`, or `"Unaffiliated"` when there is nothing to say.

---

## 4. Where a membership comes from

### 4.1 Purchase — the main path, and it already exists

`PricingService::entryFeeLinesFor` charges a programme's registration and administration
fees the first time a cart holds a paid course from that programme. Every UPRL programme
course is paid. So **every student who reads for a programme already generates a
programme-shaped, money-backed event**, and the row that records it —
`order_items.programme_id` with `kind` of `registration_fee` — is already written.

`OrderFulfilmentService::grantAccess` is therefore the natural home: alongside the
enrolments it already creates, it creates the membership. Primary if the student has none.

This is why **no new student-facing step is needed**. There is no "choose your programme"
screen to design, no signup field to add, no guest to interrogate. Registration for a
programme is a purchase, and the purchase already happens.

### 4.2 The other three ways in

| Path | Behaviour |
|---|---|
| Admin, on the user edit screen | Add/remove memberships, set primary. Writes `source = admin` |
| Bulk import | A `programme` column on `UserImport`, matched by code (CPR/DPR/NPV/NMC), unknown codes flagged in the preview as a row problem, consistent with the Section 17 vocabulary |
| Backfill command | §4.3, once |

### 4.3 The backfill — reported first, written second

Existing students have no memberships and every one of them will read "Unaffiliated" on
the day this merges. That is honest but useless, so `php artisan academic-home:backfill`
derives them from evidence already in the database, in strict order of trust:

1. **Entry-fee order items** — `order_items` with `kind IN (registration_fee,
   administration_fee)` on a paid order. This is money and it is unambiguous. If there are
   several, the earliest is primary.
2. **Enrolment placements** — for students with no entry fee, group their enrolments
   through `course_programme_part` and take the programme holding the most. A clear
   majority is a good guess; a tie is not, and a tie is left unaffiliated rather than
   coin-flipped.
3. **Everything else** — unaffiliated. Standalone short courses and Master Classes are
   deliberately outside a programme (`docs/plans/prerequisites.md` §3.2), so a student who
   only took those genuinely has no home.

The command **prints the breakdown and writes nothing** unless given `--write`. This is the
`progression:audit` pattern, and it is the pattern because the seeded demo has hundreds of
enrolments and the human should see what the guess produced before it lands.

Every row it writes carries `source = backfill`, so
`php artisan academic-home:backfill --undo` can remove exactly what it created and nothing
a human or a purchase has touched since.

---

## 5. What this may affect, and what it must not

### 5.1 What it may affect

| Surface | Effect |
|---|---|
| Student dashboard | A "Your programme" card: part, credits earned, what is next (Section 22) |
| `/my-programme` | The path view — parts laid out, cleared/current/ahead (Section 22) |
| Catalogue | A "Courses in your programme" affinity section. Filters unchanged (Section 22) |
| Admin user list | Department and programme columns, and filters for both (Section 21) |
| Reports | Real cohorts replacing the proxy; a faculty/department filter on the **student**, not just the course (Section 23) |
| Messaging & announcements | Target a programme, a part, or a department (Section 23) |
| Instructor & HOD dashboards | Scope to the staff member's own department (Section 23) |
| Certificates & transcripts | The awarding faculty, department and programme on the document (Section 24) |

### 5.2 What it must not affect — enrolment and purchase. Refused.

**Considered: restrict students to courses in their own department or programme. Refused.**

The catalogue is an open shop. `CatalogueController` serves guests, the cart accepts guests
(`docs/decisions.md`, Section 19), and `Course::inCatalogue()` publishes to the world.
Departmental restriction breaks that at the first click: a CPR student browsing a
Leadership Master Class would be shown a course they cannot buy, and a guest — who has no
department at all — could not buy anything.

It also breaks the funnel in exactly the way §0.1 of `prerequisites.md` describes for entry
fees: membership arrives *from* a purchase, so gating purchases on membership means nobody
can ever acquire one. The same deadlock, through a different door.

**Progression is the mechanism for "not yet", and it already exists.** Section 19 gates by
part, ships switched off, and is the right place for any sequencing rule. Academic home
should stay a *description* of a student, not a permission. Anything that wants to restrict
belongs in `ProgressionService` as another gate, not here.

The one authorization use that is legitimate is on the **staff** side: a head of department
seeing their own department's courses, rosters and reports. That is Section 23, it is about
`users.department_id` for staff, and it never touches a student's ability to buy or enrol.

---

## 6. Implementation

### Section 21 — the record (must be first, ships alone)

No behaviour changes for any existing screen except the admin user form. Everything else
in this section is data and derivation. Reports switch from proxy to real cohorts, which is
worth having on its own even if 22–24 are never built.

1. **Migration** `add_department_to_users_and_programmes`
   - `users.department_id` nullable FK, `nullOnDelete`
   - `programmes.department_id` nullable FK, `nullOnDelete`
   - index `users(department_id)` — every Section 23 query starts here
2. **Migration** `create_programme_user_table` — §3.1, unique `(user_id, programme_id)`,
   index `(programme_id, status)` for cohort queries
3. **`App\Enums\MembershipStatus`** (`Active`, `Completed`, `Withdrawn`) and
   **`App\Enums\MembershipSource`** (`Purchase`, `Admin`, `Import`, `Backfill`), both with
   `label()`, following `EnrollmentStatus`
4. **`App\Support\Users\AcademicHome`** — the value object (§3.4)
5. **`App\Services\Users\AcademicHomeService`**
   ```php
   join(User, Programme, MembershipSource, bool $primary = null): void
   leave(User, Programme): void
   setPrimary(User, Programme): void      // clears the others, one transaction
   syncDepartment(User): void             // THE only writer of a student's department_id
   homeFor(User): AcademicHome
   homesFor(Collection<User>): Collection // batched, for tables — mirrors verdictsFor()
   ```
   `syncDepartment` is called by every mutator and by nothing else. `homesFor` exists so
   the admin user table renders 50 rows without 50 round trips.
6. **Model wiring** — `User` relations from §3.4; `Programme::department()`,
   `Programme::students()`; `Department::staff()`, `Department::programmes()`
7. **`OrderFulfilmentService::grantAccess`** — create the membership from entry-fee lines
   (§4.1), inside the existing transaction so a failed fulfilment leaves no orphan
8. **Admin user form** — a programme multi-select with a primary radio for students; a
   department select for staff; the read-only derived line for students (§3.2).
   `StoreUserRequest`/`UpdateUserRequest` validate that a department is only *typed* for
   non-students, which is what keeps the single-writer rule true at the HTTP boundary
9. **Admin user index** — department and programme columns; filters for both
10. **Programme form** — a department select. `StoreProgrammeRequest`/`UpdateProgrammeRequest`
11. **`UserImport`** — a `programme` column, matched by code, unknown code flagged in the
    preview (§4.2)
12. **Commands** — `academic-home:backfill` (`--write`, `--undo`) and
    `academic-home:audit` (`--fix`), §3.2 and §4.3
13. **Seeder** — programmes get departments; the demo students get memberships, including
    one unaffiliated student and one holding two programmes, so both edge cases are
    clickable
14. **Tests** — a purchase creates a primary membership; a second purchase in another
    programme does not steal primary; setting primary clears the previous one;
    `syncDepartment` follows the primary; a student's department cannot be set directly
    through the form; the backfill prefers money over enrolments and leaves a tie
    unaffiliated; `--undo` removes only `backfill` rows; an unaffiliated user renders
    everywhere without a null error

### Section 22 — the student's path

15. `/my-programme` — parts in order, each cleared / current / ahead, credits earned
    against the bar, courses within each part with their status. Built on
    `ProgressionService::isPartCleared` and `creditsEarnedIn`, which already exist and
    already compute exactly this. This section is the view; the arithmetic is done.
16. Student dashboard card — current part, credits, the next course to take
17. Catalogue — a "In your programme" section above the grid for members. Filters untouched
18. Empty state for the unaffiliated: what a programme is and how to join one, not a blank
    panel

### Section 23 — staff scope and targeting

19. Reports gain a filter on the **student's** faculty/department; `ComplianceReport` gains
    a real `programme` cohort beside the existing enrolment proxy. The proxy stays — "every
    student who took this department's courses" is a legitimate question, it is just not
    the only one. Update its docblock and the Section 10 entry in `docs/decisions.md`
    rather than deleting them
20. Messaging and announcements — target a programme, a part, or a department, through the
    existing contact-picker
21. `Permission::DepartmentScopedView` and a policy trait so an instructor or HOD sees their
    own department's courses, rosters and reports. **Staff only** (§5.2)
22. Instructor dashboard scoped to their department

### Section 24 — award documents

23. The certificate snapshot gains `faculty_name`, `department_name`, `programme_name`,
    frozen at issue like `student_name` and `course_title` already are. A student who
    transfers department later keeps the certificate they were awarded
24. Template tokens for the three, and the default template updated to use them
25. A transcript document listing courses grouped by programme part

---

## 7. Problems that need deciding, not just coding

### 7.1 This does not change Section 19

`docs/plans/prerequisites.md` §6.2 warns: *"if UPRL later wants 'you are a CPR student,
here is your path', that does need an explicit membership record, and it would change this
design."*

Having designed it, the warning turns out to apply to the **view**, not to the **gate**.
Progression must stay per-course and per-placement, because a student who is not a member
of anything may still buy a single paper out of interest and must still be evaluated
correctly. Membership adds a *starting point for rendering* — which programme's ladder to
draw — and answers nothing that `ProgressionService` asks.

**`ProgressionService` gets no new parameter and no new branch.** Section 22 calls the
methods that already exist. If a future session finds itself editing `check()` to take a
membership, something has gone wrong; re-read this paragraph first.

### 7.2 The unaffiliated student is a supported state, not an error

Guests buy courses. Short courses and Master Classes sit outside programmes by design.
Somebody will always have no programme, therefore no department, therefore no faculty, and
this must never be a null-pointer or an empty box.

`AcademicHome::label()` returns `"Unaffiliated"`. Every list column, filter, report and
document renders that word. The `/my-programme` empty state explains what a programme is
and how to join one, which is a small piece of marketing in the right place. Item 14's
final test exists specifically to hold this line.

### 7.3 A student in two programmes

Supported, and the primary flag decides what shows in the singular places — the dashboard
card, the user table column, the derived department. `/my-programme` shows all of them,
with the primary first. The alternative, forcing a choice at join time, would make the
second purchase fail for a reason the student cannot act on.

### 7.4 Completing a programme does not clear the home

`status = completed` keeps the membership and keeps the department. An alumnus of CPR is
still a CPR alumnus, reports want to find them, and their certificate says so. Only
`withdrawn` releases primary — and then `syncDepartment` re-derives from whatever remains,
which may be nothing.

### 7.5 The backfill is a guess, and is labelled as one

§4.3's second tier — majority of enrolments — will be wrong for some students. That is
accepted, on the condition that it is visible: `source = backfill` is stored, the audit
command reports it, and `--undo` reverses exactly it. Nobody should ever have to work out
by hand which rows the machine invented.

### 7.6 Renaming and deleting a department

`nullOnDelete` on both new foreign keys means deleting a department orphans rather than
cascades — students and programmes fall back to unaffiliated rather than disappearing.
Certificates are unaffected because Section 24 freezes the names into the snapshot.
Whether deleting a department that still has students should be *refused* outright is a
Section 16-shaped question (`restrict_deletes_that_would_erase_student_work`) and is left
open here deliberately; `nullOnDelete` is the safe default until somebody decides.

---

## 8. Sizing

| Section | Scope | Weight |
|---|---|---|
| 21 — the record | 2 migrations, 1 service, 2 commands, admin UI, imports, seeder | **medium-large** |
| 22 — the student's path | mostly view; the arithmetic already exists | medium |
| 23 — staff scope & targeting | reports, messaging targets, a policy trait | medium |
| 24 — award documents | snapshot fields, template tokens, transcript | small-medium |

The weight in 21 is not the schema, it is the backfill and the single-writer discipline —
the two places where a shortcut produces a system that quietly disagrees with itself six
months later. Item 14's tests are the deliverable that makes the rest safe.

22, 23 and 24 are genuinely optional and genuinely independent. If only one is ever built,
build 22: it is the one a student sees, and it is the smallest, because Section 19 already
did the hard part.
