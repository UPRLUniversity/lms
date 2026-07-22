<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionVersioningTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Assignment $assignment;

    private function student(): User
    {
        $student = $this->userWithRole(Role::Student->value);
        $this->course ??= Course::factory()->published()->create();
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $student->id, 'course_id' => $this->course->id]);

        return $student;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->course = Course::factory()->published()->create();
        $this->assignment = Assignment::factory()->published()->create(['course_id' => $this->course->id]);
    }

    public function test_resubmission_creates_version_n_plus_one_and_leaves_v1_untouched(): void
    {
        $student = $this->student();

        $this->actingAs($student)->post(route('submissions.store', [$this->course, $this->assignment]), [
            'body' => '<p>First draft of my argument.</p>',
        ])->assertRedirect(route('assignments.show', [$this->course, $this->assignment]));

        $this->actingAs($student)->post(route('submissions.store', [$this->course, $this->assignment]), [
            'body' => '<p>Improved second draft.</p>',
        ])->assertRedirect();

        $versions = $this->assignment->submissionsFor($student);
        $this->assertCount(2, $versions);
        $this->assertSame([2, 1], $versions->pluck('version')->all());

        // v1 is untouched: same content, still its own row and status.
        $v1 = $versions->firstWhere('version', 1);
        $this->assertStringContainsString('First draft', (string) $v1->body);
        $this->assertSame(SubmissionStatus::Submitted, $v1->status);

        // Both versions stay readable via the version viewer.
        $this->actingAs($student)->get(route('submissions.show', $v1))
            ->assertOk()
            ->assertSee('First draft of my argument.')
            ->assertSee('Version 1');
        $this->actingAs($student)->get(route('submissions.show', $versions->firstWhere('version', 2)))
            ->assertOk()
            ->assertSee('Improved second draft.');
    }

    public function test_files_are_stored_privately_and_attached_to_their_version(): void
    {
        Storage::fake('private');
        $student = $this->student();

        $this->actingAs($student)->post(route('submissions.store', [$this->course, $this->assignment]), [
            'body' => '',
            'files' => [
                UploadedFile::fake()->create('essay.pdf', 200, 'application/pdf'),
                UploadedFile::fake()->create('figure.png', 50, 'image/png'),
            ],
        ])->assertRedirect();

        $submission = Submission::firstOrFail();
        $files = $submission->files();

        $this->assertCount(2, $files);
        $this->assertSame('essay.pdf', $files->first()->original_name);
        $this->assertNull($files->first()->url, 'Submission files must never get a public URL');
        Storage::disk('private')->assertExists($files->first()->path);
    }

    public function test_a_version_is_only_visible_to_its_owner_instructor_and_auditor(): void
    {
        $student = $this->student();
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $student->id,
        ]);

        // Another student: forbidden.
        $stranger = $this->userWithRole(Role::Student->value);
        $this->actingAs($stranger)->get(route('submissions.show', $submission))->assertForbidden();

        // The course's instructor: allowed.
        $instructor = $this->userWithRole(Role::Instructor->value);
        $this->course->update(['created_by' => $instructor->id]);
        $this->actingAs($instructor)->get(route('submissions.show', $submission))->assertOk();

        // Auditor: read-only allowed.
        $auditor = $this->userWithRole(Role::Auditor->value);
        $this->actingAs($auditor)->get(route('submissions.show', $submission))->assertOk();
    }

    public function test_an_empty_submission_is_rejected(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->from(route('assignments.show', [$this->course, $this->assignment]))
            ->post(route('submissions.store', [$this->course, $this->assignment]), ['body' => ''])
            ->assertSessionHasErrors('submission');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_type_rules_are_enforced_server_side(): void
    {
        Storage::fake('private');
        $student = $this->student();

        // A text-only assignment refuses files.
        $textOnly = Assignment::factory()->published()
            ->type(\App\Enums\AssignmentType::Text)
            ->create(['course_id' => $this->course->id]);

        $this->actingAs($student)->post(route('submissions.store', [$this->course, $textOnly]), [
            'files' => [UploadedFile::fake()->create('essay.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('files');

        // A file-only assignment refuses typed answers.
        $fileOnly = Assignment::factory()->published()
            ->type(\App\Enums\AssignmentType::File)
            ->create(['course_id' => $this->course->id]);

        $this->actingAs($student)->post(route('submissions.store', [$this->course, $fileOnly]), [
            'body' => '<p>Typed anyway.</p>',
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('submissions', 0);
    }
}
