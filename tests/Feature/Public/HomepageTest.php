<?php

namespace Tests\Feature\Public;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_for_a_guest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(config('brand.motto'))
            ->assertSee('Create your account')
            ->assertSee('Choose your qualification');
    }

    public function test_the_homepage_renders_for_a_signed_in_user(): void
    {
        // Auth-aware CTAs: a learner who is already in should be offered their
        // dashboard, not another invitation to register.
        $user = $this->userWithRole('student');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Continue learning')
            ->assertDontSee('Create your account');
    }

    public function test_the_homepage_shows_real_seeded_programmes_and_courses(): void
    {
        $programme = Programme::factory()->create([
            'code' => 'CPR',
            'name' => 'Professional Certificate in Public Relations',
            'tagline' => 'The entry qualification for practising public relations.',
        ]);
        $part = ProgrammePart::factory()->for($programme)->named('Part I', 1)->create();

        Course::factory()->published()->create(['title' => 'Principles of Public Relations'])
            ->programmeParts()->attach($part->id, ['is_primary' => true, 'credit_load' => 4]);

        $this->get(route('home'))
            ->assertOk()
            // The programme grid: card and a link to its landing page.
            ->assertSee('Professional Certificate in Public Relations')
            ->assertSee('The entry qualification for practising public relations.')
            ->assertSee(route('programmes.show', $programme))
            // The featured rail: the real course, linking into the catalogue.
            ->assertSee('Principles of Public Relations')
            ->assertSee(route('catalogue.show', Course::first()));
    }

    public function test_the_homepage_never_leaks_a_draft_or_an_enrolled_only_course(): void
    {
        // The featured rail runs through Course::inCatalogue(), the same gate the public
        // catalogue uses. If that ever slips, this is the section that leaks first.
        Course::factory()->draft()->create(['title' => 'Unfinished Draft Paper']);
        Course::factory()->review()->create(['title' => 'Paper Awaiting Review']);
        Course::factory()->archived()->create(['title' => 'Retired Paper']);
        Course::factory()->published()->enrolledOnly()->create(['title' => 'Members Only Paper']);
        Course::factory()->published()->create(['title' => 'Open Public Paper']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Open Public Paper')
            ->assertDontSee('Unfinished Draft Paper')
            ->assertDontSee('Paper Awaiting Review')
            ->assertDontSee('Retired Paper')
            ->assertDontSee('Members Only Paper');
    }

    public function test_an_inactive_programme_is_not_offered_on_the_homepage(): void
    {
        Programme::factory()->create(['name' => 'Live Qualification']);
        Programme::factory()->inactive()->create(['name' => 'Retired Qualification']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Live Qualification')
            ->assertDontSee('Retired Qualification');
    }

    public function test_the_stats_band_counts_real_rows_in_one_pass(): void
    {
        Course::factory()->published()->count(3)->create();
        Course::factory()->draft()->create();                    // not on offer
        Programme::factory()->count(2)->create();
        Programme::factory()->inactive()->create();              // not counted

        $this->userWithRole('instructor');
        $this->userWithRole('instructor', ['is_active' => false]);   // not counted

        $student = $this->userWithRole('student');
        $other = User::factory()->create();

        // Two enrolments for one student must still be one learner.
        $courses = Course::query()->inCatalogue()->take(2)->get();
        foreach ($courses as $course) {
            Enrollment::factory()->create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active,
            ]);
        }
        Enrollment::factory()->create([
            'user_id' => $other->id,
            'course_id' => $courses->first()->id,
            'status' => EnrollmentStatus::Pending,   // never took a seat, not a learner
        ]);

        $stats = app(\App\Services\Site\PublicSiteService::class)->stats();

        $this->assertSame(3, $stats['courses']);
        $this->assertSame(2, $stats['programmes']);
        $this->assertSame(1, $stats['instructors']);
        $this->assertSame(1, $stats['learners']);
    }

    public function test_the_homepage_search_deep_links_into_the_catalogue(): void
    {
        // The hero's search box is a plain GET form pointed at the catalogue, so the
        // filters it hands over must be the ones the catalogue actually reads.
        $programme = Programme::factory()->create(['code' => 'DPR', 'slug' => 'dpr']);
        $part = ProgrammePart::factory()->for($programme)->named('Part I', 1)->create();

        Course::factory()->published()->create(['title' => 'Comparative PR Systems'])
            ->programmeParts()->attach($part->id, ['is_primary' => true]);
        Course::factory()->published()->create(['title' => 'Something Unrelated']);

        $this->get(route('home'))->assertOk()->assertSee(route('catalogue.index'));

        $this->get(route('catalogue.index', ['q' => 'Comparative', 'programme' => 'dpr']))
            ->assertOk()
            ->assertSee('Comparative PR Systems')
            ->assertDontSee('Something Unrelated');
    }
}
