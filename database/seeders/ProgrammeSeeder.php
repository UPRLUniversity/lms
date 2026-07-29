<?php

namespace Database\Seeders;

use App\Enums\CourseLevel;
use App\Enums\CourseRequirement;
use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\LessonType;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Department;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Seeder;

/**
 * The NIPR qualification structure, seeded verbatim from the published
 * "Courses Offered, Requirements and Fees" schedule (25 August 2022).
 *
 * Three programmes, because the fee schedule prices exactly three tiers —
 * Certificate (CPR), Diploma (DPR) and Professional (the Variant). The reference
 * site also shows a Master Class, but the schedule states no courses or fees for
 * it, so it is not invented here; an admin adds it from Programmes in one step.
 *
 * Runs AFTER CourseSeeder so the faculty/department hierarchy already exists —
 * these courses join it rather than duplicating it. Idempotent on course code and
 * programme code, so re-seeding never doubles anything up.
 *
 * The case worth demonstrating: five papers are placed in TWO programmes at once
 * (CPR 112, CPR 115, CPR 216, CPR 219, DPR 411 all reappear in the Professional
 * Variant). Their CPR/DPR placement is marked primary, so the course keeps its home
 * programme's per-paper price rather than the Variant's higher one.
 */
class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::role(Role::Instructor->value)->orderBy('id')->get();
        if ($instructors->isEmpty()) {
            $instructors = collect([User::factory()->create()->assignRole(Role::Instructor->value)]);
        }

        $departments = Department::query()->pluck('id', 'name');
        $parts = $this->seedProgrammes();
        $catalogue = $this->catalogue();

        // code => [[part, credit, requirement, primary], …]
        $placements = [];

        foreach ($this->curriculum() as $partKey => $rows) {
            foreach ($rows as $code => $row) {
                $placements[$code][] = [
                    'part' => $parts[$partKey],
                    'credit_load' => $row[0],
                    'requirement' => $row[1],
                    'primary' => $row[2] ?? false,
                ];
            }
        }

        $i = 0;
        foreach ($placements as $code => $rows) {
            $meta = $catalogue[$code] ?? null;
            if ($meta === null) {
                continue;   // a curriculum row with no catalogue entry is a data bug, not a crash
            }

            $course = $this->upsertCourse($code, $meta, $departments, $instructors[$i % $instructors->count()]);
            $i++;

            $course->syncProgrammePlacements(array_map(fn (array $row) => [
                'programme_part_id' => $row['part']->id,
                'credit_load' => $row['credit_load'],
                'requirement' => $row['requirement']?->value,
                'is_primary' => $row['primary'],
            ], $rows));
        }

        $this->placeExistingDemoCourses($parts);
    }

    /*
    |--------------------------------------------------------------------------
    | Programmes & parts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, ProgrammePart> keyed "CPR:I", "DPR:II", "NPV:1" …
     */
    private function seedProgrammes(): array
    {
        // [code, name, tagline, registration, administration, per paper, parts]
        // Fees are the published schedule as at 25 August 2022.
        $definitions = [
            [
                'CPR', 'Professional Certificate in Public Relations',
                'The entry qualification for practising public relations in Nigeria.',
                20000, 25000, 7000,
                [['I', 'Part I', 24], ['II', 'Part II', null]],
            ],
            [
                'DPR', 'Professional Diploma in Public Relations',
                'Strategic, managerial public relations for practitioners moving into leadership.',
                25000, 30000, 10000,
                [['I', 'Part I', 21], ['II', 'Part II', null]],
            ],
            [
                'NPV', 'Professional Variant',
                'The accelerated route for candidates exempted from the earlier stages.',
                30000, 35000, 15000,
                [['1', 'Part 1', null], ['2', 'Part 2', null], ['3', 'Part 3', null]],
            ],
            [
                // Short courses outside the examined NIPR ladder. The fee schedule
                // states nothing for it, so all three fees stay 0 and its courses
                // remain free — which is also what keeps every pre-Section-11 demo
                // flow and test behaving exactly as before.
                'NMC', 'Master Class',
                'Short, practical courses open to everyone — no examination, no prerequisites.',
                0, 0, 0,
                [['F', 'Foundation', null], ['A', 'Advanced', null]],
            ],
        ];

        $parts = [];

        foreach ($definitions as $position => [$code, $name, $tagline, $registration, $administration, $perPaper, $partRows]) {
            $programme = Programme::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    // Slug is the code, so the public filter reads /courses?programme=cpr.
                    'slug' => Programme::where('code', $code)->value('slug') ?? Slug::unique(Programme::class, $code),
                    'tagline' => $tagline,
                    'description' => '<p>'.$this->programmeBlurb($name).'</p>',
                    'registration_fee' => $registration,
                    'administration_fee' => $administration,
                    'per_paper_fee' => $perPaper,
                    'position' => $position + 1,
                    'is_active' => true,
                ],
            );

            foreach ($partRows as $partPosition => [$key, $partName, $creditTarget]) {
                $parts["{$code}:{$key}"] = ProgrammePart::updateOrCreate(
                    ['programme_id' => $programme->id, 'slug' => \Illuminate\Support\Str::slug($partName)],
                    [
                        'name' => $partName,
                        'credit_target' => $creditTarget,
                        'position' => $partPosition + 1,
                    ],
                );
            }
        }

        return $parts;
    }

    private function programmeBlurb(string $name): string
    {
        return "The {$name} is awarded by the Nigerian Institute of Public Relations and delivered at "
            .config('brand.university').'. Candidates work through each part in turn, sitting the '
            .'papers listed for that stage, and are examined against the Institute’s published standards.';
    }

    /*
    |--------------------------------------------------------------------------
    | Courses
    |--------------------------------------------------------------------------
    */

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $departments
     * @param  array{0: string, 1: string, 2: string, 3: string}  $meta  [title, department, level, summary]
     */
    private function upsertCourse(string $code, array $meta, $departments, User $lead): Course
    {
        [$title, $departmentName, $level, $summary] = $meta;

        $course = Course::updateOrCreate(
            ['code' => $code],
            [
                'title' => "{$code}: {$title}",
                'slug' => Course::where('code', $code)->value('slug') ?? Slug::unique(Course::class, "{$code} {$title}"),
                'department_id' => $departments[$departmentName] ?? null,
                'level' => $level,
                'summary' => $summary,
                'description' => '<p>'.$summary.'</p><p>This paper is examined under the Nigerian Institute of '
                    .'Public Relations syllabus. Work through the material, complete the assessments, and sit the '
                    .'paper at the end of the stage.</p>',
                'learning_objectives' => [
                    'Explain the core concepts of '.$title.'.',
                    'Apply them to a Nigerian public relations context.',
                    'Meet the NIPR examination standard for this paper.',
                ],
                'status' => CourseStatus::Published->value,
                'visibility' => CourseVisibility::PublicCatalogue->value,
                'created_by' => $lead->id,
                'published_at' => now(),
            ],
        );

        $course->instructors()->syncWithoutDetaching([$lead->id => ['is_lead' => true]]);

        // A single starter module so the course is publishable and the catalogue's
        // lesson count is honest. Deliberately light — these ~45 papers exist to make
        // the taxonomy real, not to be sat end-to-end; the eight hand-written demo
        // courses from CourseSeeder are the ones with full curricula.
        if ($course->modules()->doesntExist()) {
            $module = $course->modules()->create(['title' => 'Orientation', 'position' => 1]);

            $module->lessons()->createMany([
                [
                    'title' => 'About this paper',
                    'type' => LessonType::Text->value,
                    'position' => 1,
                    'duration_minutes' => 10,
                    'is_free_preview' => true,
                    'content_text' => "<p>{$summary}</p><h3>How this paper runs</h3><ul>"
                        .'<li>Study the material in your own time.</li>'
                        .'<li>Complete the assessments set by your lecturer.</li>'
                        .'<li>Sit the paper at the end of the stage.</li></ul>',
                ],
                [
                    'title' => 'NIPR syllabus and examination standards',
                    'type' => LessonType::ExternalLink->value,
                    'position' => 2,
                    'duration_minutes' => 8,
                    'external_url' => 'https://nipr.org.ng',
                ],
            ]);

            $course->update(['duration_minutes' => $course->lessons()->sum('duration_minutes')]);
        }

        return $course;
    }

    /**
     * The eight hand-written demo courses from CourseSeeder predate programmes. They go
     * into the Master Class, NOT into the examined CPR/DPR parts.
     *
     * That is deliberate. Dropping them into CPR Part I pushed its counted credits from
     * 24 to 29 and DPR Part I from 21 to 24, so the admin screen flagged a mismatch on a
     * curriculum that is in fact transcribed correctly — the seed data was lying about
     * the prospectus. The Master Class keeps the NIPR parts numerically faithful while
     * still giving the programme filter rich, fully-built courses to return.
     *
     * @param  array<string, ProgrammePart>  $parts
     */
    private function placeExistingDemoCourses(array $parts): void
    {
        $map = [
            'PRL101' => ['NMC:F', null, CourseRequirement::Compulsory],
            'LDS110' => ['NMC:F', null, CourseRequirement::Compulsory],
            'JRN210' => ['NMC:F', null, CourseRequirement::Compulsory],
            'PAD120' => ['NMC:F', null, CourseRequirement::Compulsory],
            'PRL220' => ['NMC:A', null, CourseRequirement::Compulsory],
            'PRL305' => ['NMC:A', null, CourseRequirement::Compulsory],
            'LDS201' => ['NMC:A', null, CourseRequirement::Compulsory],
            'SCM330' => ['NMC:A', null, CourseRequirement::Compulsory],
        ];

        foreach ($map as $code => [$partKey, $credit, $requirement]) {
            $course = Course::where('code', $code)->first();
            if (! $course || ! isset($parts[$partKey])) {
                continue;
            }

            $course->syncProgrammePlacements([[
                'programme_part_id' => $parts[$partKey]->id,
                'credit_load' => $credit,
                'requirement' => $requirement->value,
                'is_primary' => true,
            ]]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The published curriculum
    |--------------------------------------------------------------------------
    */

    /**
     * part key => [course code => [credit load, requirement, is primary]]
     *
     * Transcribed from the schedule. CPR 115's status column is blank in the source;
     * it is treated as compulsory, consistent with every other 3-credit CPR Part I
     * paper and with the stated 24-credit total, which only reconciles if 115 is
     * inside it.
     *
     * The Professional Variant lists neither credits nor status, so its placements
     * carry a null credit load. Its papers are marked compulsory because the Variant
     * is a fixed syllabus — a candidate sits all of them, there is nothing to choose.
     *
     * @return array<string, array<string, array{0: int|null, 1: CourseRequirement, 2?: bool}>>
     */
    private function curriculum(): array
    {
        $C = CourseRequirement::Compulsory;
        $R = CourseRequirement::RequiredElective;
        $E = CourseRequirement::Elective;
        $G = CourseRequirement::Gns;

        return [
            // Sums to 28 listed; 24 once the two pure electives are excluded — the
            // figure the prospectus prints.
            'CPR:I' => [
                'GNS101' => [2, $G, true],
                'GNS102' => [2, $G, true],
                'CPR111' => [2, $C, true],
                'CPR112' => [3, $C, true],   // also NPV Part 1 — CPR is primary
                'CPR113' => [3, $C, true],
                'CPR114' => [3, $C, true],
                'CPR115' => [3, $C, true],   // also NPV Part 1 — CPR is primary
                'CPR116' => [3, $C, true],
                'CPR117' => [3, $R, true],
                'CPR118' => [2, $E, true],
                'CPR119' => [2, $E, true],
            ],
            'CPR:II' => [
                'CPR211' => [2, $C, true],
                'CPR212' => [3, $C, true],
                'CPR213' => [2, $C, true],
                'CPR214' => [3, $C, true],
                'CPR215' => [2, $C, true],
                'CPR216' => [3, $C, true],   // also NPV Part 2 — CPR is primary
                'CPR217' => [3, $C, true],
                'CPR218' => [2, $E, true],
                'CPR219' => [2, $E, true],   // also NPV Part 2 — CPR is primary
            ],
            // 18 compulsory + 3 required elective = the stated 21.
            'DPR:I' => [
                'DPR311' => [3, $C, true],
                'DPR312' => [3, $C, true],
                'DPR313' => [3, $C, true],
                'DPR314' => [3, $C, true],
                'DPR315' => [3, $C, true],
                'DPR316' => [3, $C, true],
                'DPR317' => [3, $R, true],
            ],
            'DPR:II' => [
                'DPR411' => [3, $C, true],   // also NPV Part 2 — DPR is primary
                'DPR412' => [3, $C, true],
                'DPR413' => [3, $C, true],
                'DPR414' => [3, $C, true],
                'DPR415' => [6, $C, true],
            ],
            // Variant: no credits or status published. Dual-placed papers are NOT
            // primary here, so they keep their home programme's per-paper fee.
            'NPV:1' => [
                'CPR112' => [null, $C],
                'CPR123' => [null, $C, true],
                'CPR115' => [null, $C],
                'CPR126' => [null, $C, true],
            ],
            'NPV:2' => [
                'CPR216' => [null, $C],
                'CPR219' => [null, $C],
                'DPR426' => [null, $C, true],
                'DPR411' => [null, $C],
            ],
            'NPV:3' => [
                'DPR321' => [null, $C, true],
                'DPR323' => [null, $C, true],
                'DPR318' => [null, $C, true],
                'DPR423' => [null, $C, true],
                'DPR425' => [null, $C, true],
            ],
        ];
    }

    /**
     * code => [title, department, level, summary]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private function catalogue(): array
    {
        $pr = 'Department of Public Relations';
        $jm = 'Department of Journalism & Media';
        $sc = 'Department of Strategic Communication';
        $ol = 'Department of Organisational Leadership';
        $ds = 'Department of Development Studies';
        $pa = 'Department of Public Administration';

        $cert = CourseLevel::Certificate->value;
        $prof = CourseLevel::Professional->value;

        return [
            // ── CPR Part I ────────────────────────────────────────────────────
            'GNS101' => ['Basic Communication Skills', $jm, $cert, 'Read, write, listen and speak with the precision professional communication demands.'],
            'GNS102' => ['Nigerian History & Citizenship Education', $ds, $cert, 'The making of modern Nigeria and the duties of citizenship that shape public life.'],
            'CPR111' => ['Communication Theories', $sc, $cert, 'The models that explain how messages move, persuade and sometimes fail.'],
            'CPR112' => ['Principles of Public Relations', $pr, $cert, 'The foundations of the discipline: publics, reputation, mutual understanding and the practitioner’s role.'],
            'CPR113' => ['Principles of Psychology', $ol, $cert, 'How people perceive, decide and behave — the ground every persuasion strategy stands on.'],
            'CPR114' => ['Writing for the Media', $jm, $cert, 'News writing, press releases and features that editors actually run.'],
            'CPR115' => ['PR Media and Methods', $pr, $cert, 'Choosing and combining the channels and techniques a campaign runs on.'],
            'CPR116' => ['Business Management and Entrepreneurship Skills for PR', $ol, $cert, 'Run a practice as a business: planning, finance, clients and growth.'],
            'CPR117' => ['Quantitative Methods for Communication Practice', $sc, $cert, 'Sampling, surveys and statistics for practitioners who must prove what worked.'],
            'CPR118' => ['Nigerian Cultural Studies & Intercultural Communication', $ds, $cert, 'Communicating credibly across Nigeria’s languages, faiths and cultures.'],
            'CPR119' => ['ICTs for Public Relations', $sc, $cert, 'The digital tools of contemporary practice, from monitoring to publishing.'],

            // ── CPR Part II ───────────────────────────────────────────────────
            'CPR211' => ['Economics and Development Studies for Public Relations', $ds, $cert, 'The economic forces that set the agenda a communicator has to work within.'],
            'CPR212' => ['Public Relations for Government, Public Sector & Non-Profit Organizations', $pa, $cert, 'Serving the public interest: government communication, advocacy and accountability.'],
            'CPR213' => ['Stakeholders Mapping and Relationship Management', $pr, $cert, 'Identify who matters, understand what they want, and manage the relationship deliberately.'],
            'CPR214' => ['Public Relations for Business & Industry', $pr, $cert, 'Corporate communication, investor relations and industrial reputation.'],
            'CPR215' => ['Social Media and Public Relations', $sc, $cert, 'Strategy, community and crisis on the platforms where publics now gather.'],
            'CPR216' => ['Research & Evaluation in Public Relations', $sc, $cert, 'Design the research, run it, and prove the campaign moved something.'],
            'CPR217' => ['Protocols & Events Management', $pr, $cert, 'Precedence, ceremony and the logistics of events that reflect well on the organisation.'],
            'CPR218' => ['Public Relations Laws and Ethics in Nigeria', $pa, $cert, 'The statutes, the NIPR code, and the judgement calls between them.'],
            'CPR219' => ['Integrated Marketing Communications', $sc, $cert, 'One voice across advertising, PR, digital and direct.'],

            // ── Variant-only Part 1 ───────────────────────────────────────────
            'CPR123' => ['Industrial Psychology and Sociology', $ol, $prof, 'People and groups at work: motivation, structure, conflict and change.'],
            'CPR126' => ['Entrepreneurship Skills for Public Relations and Business Management', $ol, $prof, 'Build and run a consultancy — proposition, pricing, pitching and delivery.'],

            // ── DPR Part I ────────────────────────────────────────────────────
            'DPR311' => ['Public Relations and Strategic Management, Policy & Corporate Planning', $sc, $prof, 'Put communication in the boardroom: strategy, policy and the corporate plan.'],
            'DPR312' => ['Corporate, Product and Service Brands Management', $pr, $prof, 'Build, position and defend a brand across corporate, product and service lines.'],
            'DPR313' => ['Media Relations, Procurement and Performance Management', $jm, $prof, 'Working with media professionally: relationships, buying and measured performance.'],
            'DPR314' => ['Marketing & Advertising in Public Relations', $sc, $prof, 'Where marketing and advertising meet public relations, and how to make them pull together.'],
            'DPR315' => ['Strategic Communication and Crisis Management', $sc, $prof, 'Prepare, respond and recover when reputation is on the line.'],
            'DPR316' => ['Research Methods for Public Relations', $sc, $prof, 'Rigorous method for practitioners: design, instruments, analysis and reporting.'],
            'DPR317' => ['Public Relations Seminar', $pr, $prof, 'Present, defend and critique current practice with your peers.'],

            // ── DPR Part II ───────────────────────────────────────────────────
            'DPR411' => ['Financial Literacy for Public Relations', $ol, $prof, 'Read the accounts, speak the language of finance, and communicate results credibly.'],
            'DPR412' => ['Comparative Public Relations Systems', $ds, $prof, 'How practice differs across countries and what that means for Nigerian practitioners.'],
            'DPR413' => ['Public Relations for International Organizations', $pa, $prof, 'Communicating for multilaterals, NGOs and cross-border institutions.'],
            'DPR414' => ['Project and Public Relations Campaign Management', $pr, $prof, 'Run a campaign as a project: scope, schedule, budget, risk and delivery.'],
            'DPR415' => ['Research Project in Public Relations', $sc, $prof, 'An original, supervised research project — the capstone of the Diploma.'],

            // ── Variant-only Parts 2 & 3 ──────────────────────────────────────
            'DPR426' => ['Global Public Relations', $ds, $prof, 'Practice across borders, cultures and time zones.'],
            'DPR321' => ['Public Relations Campaign Planning and Strategies', $pr, $prof, 'From situation analysis to strategy, tactics and evaluation.'],
            'DPR323' => ['Public Relations Consultancy Management', $ol, $prof, 'Winning, servicing and retaining consultancy clients profitably.'],
            'DPR318' => ['Public Relations Laws and Ethics and Corporate Governance', $pa, $prof, 'Law, professional ethics and the governance duties of the senior practitioner.'],
            'DPR423' => ['Lobbying and Advocacy', $pa, $prof, 'Influencing policy legitimately, and the line that must not be crossed.'],
            'DPR425' => ['Case Studies in Public Relations', $pr, $prof, 'Dissect real campaigns — what worked, what failed and why.'],
        ];
    }
}
