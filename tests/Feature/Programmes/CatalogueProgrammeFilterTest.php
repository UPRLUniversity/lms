<?php

namespace Tests\Feature\Programmes;

use App\Enums\CourseRequirement;
use App\Models\Course;
use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueProgrammeFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Programme, 1: ProgrammePart, 2: ProgrammePart}
     */
    private function programmeWithTwoParts(string $code): array
    {
        $programme = Programme::factory()->create(['code' => $code, 'slug' => strtolower($code)]);

        return [
            $programme,
            ProgrammePart::factory()->for($programme)->named('Part I', 1)->create(),
            ProgrammePart::factory()->for($programme)->named('Part II', 2)->create(),
        ];
    }

    public function test_the_catalogue_filters_by_programme(): void
    {
        [, $cprPartOne] = $this->programmeWithTwoParts('CPR');
        [, $dprPartOne] = $this->programmeWithTwoParts('DPR');

        Course::factory()->published()->create(['title' => 'Principles of PR'])
            ->programmeParts()->attach($cprPartOne->id);
        Course::factory()->published()->create(['title' => 'Comparative PR Systems'])
            ->programmeParts()->attach($dprPartOne->id);

        $this->get(route('catalogue.index', ['programme' => 'cpr']))
            ->assertOk()
            ->assertSee('Principles of PR')
            ->assertDontSee('Comparative PR Systems');
    }

    public function test_the_catalogue_filters_by_part_within_a_programme(): void
    {
        [, $partOne, $partTwo] = $this->programmeWithTwoParts('CPR');

        Course::factory()->published()->create(['title' => 'Communication Theories'])
            ->programmeParts()->attach($partOne->id);
        Course::factory()->published()->create(['title' => 'Protocols and Events'])
            ->programmeParts()->attach($partTwo->id);

        $this->get(route('catalogue.index', ['programme' => 'cpr', 'part' => 'part-i']))
            ->assertOk()
            ->assertSee('Communication Theories')
            ->assertDontSee('Protocols and Events');
    }

    public function test_a_part_slug_alone_does_not_filter_across_programmes(): void
    {
        // Part slugs are unique per programme, so "part-i" is meaningless on its own.
        // Without a programme it must be ignored rather than silently matching all three.
        [, $cprPartOne] = $this->programmeWithTwoParts('CPR');
        [, $dprPartOne] = $this->programmeWithTwoParts('DPR');

        Course::factory()->published()->create(['title' => 'CPR Paper'])->programmeParts()->attach($cprPartOne->id);
        Course::factory()->published()->create(['title' => 'DPR Paper'])->programmeParts()->attach($dprPartOne->id);

        $this->get(route('catalogue.index', ['part' => 'part-i']))
            ->assertOk()
            ->assertSee('CPR Paper')
            ->assertSee('DPR Paper');
    }

    public function test_a_dual_placed_course_appears_under_both_programmes(): void
    {
        [, $cprPartOne] = $this->programmeWithTwoParts('CPR');
        [, $npvPartOne] = $this->programmeWithTwoParts('NPV');

        $course = Course::factory()->published()->create(['title' => 'Principles of Public Relations']);
        $course->programmeParts()->attach($cprPartOne->id, ['credit_load' => 3, 'is_primary' => true]);
        $course->programmeParts()->attach($npvPartOne->id);

        $this->get(route('catalogue.index', ['programme' => 'cpr']))->assertOk()->assertSee('Principles of Public Relations');
        $this->get(route('catalogue.index', ['programme' => 'npv']))->assertOk()->assertSee('Principles of Public Relations');
    }

    public function test_a_dual_placed_course_is_listed_once_not_once_per_placement(): void
    {
        // whereHas on a many-to-many must not fan the result out into duplicate rows.
        [, $partOne, $partTwo] = $this->programmeWithTwoParts('CPR');

        $course = Course::factory()->published()->create(['title' => 'Duplicated Paper']);
        $course->programmeParts()->attach([$partOne->id, $partTwo->id]);

        $response = $this->get(route('catalogue.index', ['programme' => 'cpr']))->assertOk();

        $this->assertSame(1, $response->viewData('courses')->total());
    }

    public function test_the_course_page_shows_every_qualification_it_counts_toward(): void
    {
        [$cpr, $cprPartOne] = $this->programmeWithTwoParts('CPR');
        [$npv, $npvPartOne] = $this->programmeWithTwoParts('NPV');

        $course = Course::factory()->published()->create();
        $course->programmeParts()->attach($cprPartOne->id, [
            'credit_load' => 3,
            'requirement' => CourseRequirement::Compulsory->value,
            'is_primary' => true,
        ]);
        $course->programmeParts()->attach($npvPartOne->id);

        $this->get(route('catalogue.show', $course))
            ->assertOk()
            ->assertSee($cpr->name)
            ->assertSee($npv->name)
            ->assertSee('Compulsory')
            ->assertSee('3 credits');
    }

    public function test_an_inactive_programme_is_not_offered_as_a_filter(): void
    {
        Programme::factory()->inactive()->create(['name' => 'Retired Programme', 'slug' => 'retired']);
        Programme::factory()->create(['name' => 'Live Programme', 'slug' => 'live']);

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('Live Programme')
            ->assertDontSee('Retired Programme');
    }
}
