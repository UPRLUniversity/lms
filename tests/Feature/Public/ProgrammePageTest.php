<?php

namespace Tests\Feature\Public;

use App\Enums\CourseRequirement;
use App\Models\Course;
use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammePageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A programme whose Part I lists 4 compulsory + 3 required-elective + 2 pure
     * elective credits. Counted = 7 against a stated target of 7; listed = 9.
     *
     * This is the CPR Part I shape in miniature — the reason credit_target exists at
     * all (see CourseRequirement::countsTowardTarget).
     */
    private function programmeWithCurriculum(): Programme
    {
        $programme = Programme::factory()->create([
            'code' => 'CPR',
            'slug' => 'cpr',
            'name' => 'Professional Certificate in Public Relations',
            'per_paper_fee' => 7000,
            'registration_fee' => 20000,
            'administration_fee' => 25000,
        ]);

        $partOne = ProgrammePart::factory()->for($programme)->named('Part I', 1)->create(['credit_target' => 7]);
        $partTwo = ProgrammePart::factory()->for($programme)->named('Part II', 2)->create(['credit_target' => null]);

        $rows = [
            [$partOne, 'Principles of Public Relations', 4, CourseRequirement::Compulsory],
            [$partOne, 'Nigerian Media Landscape', 3, CourseRequirement::RequiredElective],
            [$partOne, 'Photography for Communicators', 2, CourseRequirement::Elective],
            [$partTwo, 'Crisis Communication Practice', 5, CourseRequirement::Compulsory],
        ];

        foreach ($rows as [$part, $title, $credit, $requirement]) {
            Course::factory()->published()->create(['title' => $title, 'is_free' => false])
                ->programmeParts()->attach($part->id, [
                    'credit_load' => $credit,
                    'requirement' => $requirement->value,
                    'is_primary' => true,
                ]);
        }

        return $programme;
    }

    public function test_the_programmes_index_lists_active_programmes_for_a_guest(): void
    {
        Programme::factory()->create(['name' => 'Live Qualification']);
        Programme::factory()->inactive()->create(['name' => 'Retired Qualification']);

        $this->get(route('programmes.index'))
            ->assertOk()
            ->assertSee('Live Qualification')
            ->assertDontSee('Retired Qualification');
    }

    public function test_a_programme_page_groups_its_courses_under_their_parts(): void
    {
        $programme = $this->programmeWithCurriculum();

        $response = $this->get(route('programmes.show', $programme));

        $response->assertOk()
            ->assertSee('Professional Certificate in Public Relations')
            ->assertSee('Part I')
            ->assertSee('Part II')
            ->assertSee('Principles of Public Relations')
            ->assertSee('Crisis Communication Practice')
            // Requirement badges come off the pivot, per placement.
            ->assertSee('Compulsory')
            ->assertSee('Req. elective')
            // Credit loads, and the price inherited from the programme's per-paper fee.
            ->assertSee('₦7,000');

        // Each part deep-links into the catalogue with BOTH filters — a bare part slug
        // is ambiguous across programmes, which is why the programme rides along.
        $response->assertSee(route('catalogue.index', ['programme' => 'cpr', 'part' => 'part-i']));
    }

    public function test_a_part_reconciles_its_counted_credits_against_the_stated_target(): void
    {
        $programme = $this->programmeWithCurriculum();

        $part = $this->site()->programmeCurriculum($programme)->parts->firstWhere('name', 'Part I');

        // Compulsory (4) + required elective (3) = 7. The pure elective (2) is the
        // student's free choice on top, so it is listed but not counted.
        $this->assertSame(7, $part->creditsCounted($part->courses));
        $this->assertSame(9, $part->creditsListed($part->courses));
        $this->assertTrue($part->creditsReconcile($part->courses));

        $this->get(route('programmes.show', $programme))
            ->assertOk()
            ->assertSee('7 of 7 credits')
            ->assertSee('9 listed, including electives');
    }

    public function test_a_part_with_no_stated_target_reports_only_what_is_listed(): void
    {
        $programme = $this->programmeWithCurriculum();

        $this->get(route('programmes.show', $programme))
            ->assertOk()
            // Part II states no target, so the page must not assert a false mismatch.
            ->assertSee('5 credits listed');
    }

    public function test_a_programme_page_never_leaks_a_draft_or_enrolled_only_course(): void
    {
        $programme = Programme::factory()->create(['slug' => 'cpr']);
        $part = ProgrammePart::factory()->for($programme)->named('Part I', 1)->create();

        foreach ([
            Course::factory()->draft()->create(['title' => 'Unfinished Draft Paper']),
            Course::factory()->published()->enrolledOnly()->create(['title' => 'Members Only Paper']),
            Course::factory()->published()->create(['title' => 'Open Public Paper']),
        ] as $course) {
            $course->programmeParts()->attach($part->id, ['credit_load' => 3, 'is_primary' => true]);
        }

        $this->get(route('programmes.show', $programme))
            ->assertOk()
            ->assertSee('Open Public Paper')
            ->assertDontSee('Unfinished Draft Paper')
            ->assertDontSee('Members Only Paper');
    }

    public function test_an_inactive_programme_is_not_publicly_reachable(): void
    {
        // Switching a programme off in admin must take it off the public site the same
        // second — including anyone holding a direct link.
        $programme = Programme::factory()->inactive()->create();

        $this->get(route('programmes.show', $programme))->assertNotFound();
    }

    public function test_the_programme_page_shows_the_one_off_entry_fees(): void
    {
        $programme = $this->programmeWithCurriculum();

        $this->get(route('programmes.show', $programme))
            ->assertOk()
            ->assertSee('Registration')
            ->assertSee('₦20,000')
            ->assertSee('Administration')
            ->assertSee('₦25,000')
            ->assertSee('one-off');
    }

    private function site(): \App\Services\Site\PublicSiteService
    {
        return app(\App\Services\Site\PublicSiteService::class);
    }
}
