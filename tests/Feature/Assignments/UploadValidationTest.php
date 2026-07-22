<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private Assignment $assignment;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->course = Course::factory()->published()->create();
        $this->assignment = Assignment::factory()->published()->create(['course_id' => $this->course->id]);
        $this->student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
    }

    private function submitFiles(array $files)
    {
        return $this->actingAs($this->student)
            ->from(route('assignments.show', [$this->course, $this->assignment]))
            ->post(route('submissions.store', [$this->course, $this->assignment]), ['files' => $files]);
    }

    public function test_disallowed_file_types_are_rejected_gracefully(): void
    {
        $this->submitFiles([UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload')])
            ->assertSessionHasErrors(['files.0']);

        $errors = session('errors')->get('files.0');
        $this->assertStringContainsString("isn't accepted", $errors[0]);
        $this->assertDatabaseCount('submissions', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_oversize_files_are_rejected_gracefully(): void
    {
        $tooBig = UploadedFile::fake()->create('thesis.pdf', 20481, 'application/pdf'); // > 20MB

        $this->submitFiles([$tooBig])->assertSessionHasErrors(['files.0']);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_more_than_five_files_are_rejected(): void
    {
        $files = collect(range(1, 6))
            ->map(fn ($i) => UploadedFile::fake()->create("part{$i}.pdf", 10, 'application/pdf'))
            ->all();

        $this->submitFiles($files)->assertSessionHasErrors(['files']);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_instructor_resource_uploads_are_validated_too(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $this->course->update(['created_by' => $instructor->id]);

        $this->actingAs($instructor)
            ->from(route('assignments.edit', [$this->course, $this->assignment]))
            ->post(route('assignments.resources.store', [$this->course, $this->assignment]), [
                'file' => UploadedFile::fake()->create('script.sh', 10, 'application/x-sh'),
            ])->assertSessionHasErrors('file');

        $this->actingAs($instructor)
            ->post(route('assignments.resources.store', [$this->course, $this->assignment]), [
                'file' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
            ])->assertRedirect();

        $this->assertCount(1, $this->assignment->resources());
    }
}
