<?php

namespace Tests\Feature\Courses;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_public_courses_are_listed(): void
    {
        $published = Course::factory()->published()->create(['title' => 'Visible Course']);
        $draft = Course::factory()->draft()->create(['title' => 'Hidden Draft']);
        $review = Course::factory()->review()->create(['title' => 'Hidden Review']);
        $archived = Course::factory()->archived()->create(['title' => 'Hidden Archived']);
        $enrolledOnly = Course::factory()->published()->enrolledOnly()->create(['title' => 'Hidden Private']);

        $response = $this->get(route('catalogue.index'));

        $response->assertOk()->assertSee('Visible Course');
        $response->assertDontSee('Hidden Draft');
        $response->assertDontSee('Hidden Review');
        $response->assertDontSee('Hidden Archived');
        $response->assertDontSee('Hidden Private');
    }

    public function test_a_stranger_can_view_a_published_course_detail(): void
    {
        $course = Course::factory()->published()->create();

        $this->get(route('catalogue.show', $course))->assertOk()->assertSee($course->title);
    }

    public function test_a_stranger_cannot_reach_a_draft_course_by_url(): void
    {
        $draft = Course::factory()->draft()->create();
        $review = Course::factory()->review()->create();
        $enrolledOnly = Course::factory()->published()->enrolledOnly()->create();

        $this->get(route('catalogue.show', $draft))->assertNotFound();
        $this->get(route('catalogue.show', $review))->assertNotFound();
        $this->get(route('catalogue.show', $enrolledOnly))->assertNotFound();
    }

    public function test_free_preview_video_is_embedded_on_the_detail_page(): void
    {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->video('https://www.youtube.com/watch?v=dQw4w9WgXcQ')->freePreview()->create([
            'title' => 'Free intro',
        ]);

        $this->get(route('catalogue.show', $course))
            ->assertOk()
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ');
    }

    public function test_catalogue_filters_by_level(): void
    {
        $undergrad = Course::factory()->published()->create(['title' => 'Undergrad PR', 'level' => 'undergraduate']);
        $postgrad = Course::factory()->published()->create(['title' => 'Postgrad Leadership', 'level' => 'postgraduate']);

        $this->get(route('catalogue.index', ['level' => 'undergraduate']))
            ->assertSee('Undergrad PR')
            ->assertDontSee('Postgrad Leadership');
    }

    public function test_catalogue_sorts_by_title(): void
    {
        Course::factory()->published()->create(['title' => 'Zebra Studies']);
        Course::factory()->published()->create(['title' => 'Alpha Studies']);

        $response = $this->get(route('catalogue.index', ['sort' => 'title']))->assertOk();

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Zebra Studies'),
            strpos($content, 'Alpha Studies'),
            'Alpha should appear before Zebra when sorting by title.',
        );
    }

    public function test_an_ajax_request_returns_only_the_results_grid(): void
    {
        Course::factory()->published()->create(['title' => 'Ajax Course']);

        $response = $this->get(route('catalogue.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $response->assertSee('Ajax Course');
        // The partial excludes the page chrome (hero + public nav).
        $response->assertDontSee('Find a course worth your time.');
    }
}
