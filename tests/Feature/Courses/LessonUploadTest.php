<?php

namespace Tests\Feature\Courses;

use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonUploadTest extends TestCase
{
    use RefreshDatabase;

    private function ownedModule(User $instructor): Module
    {
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        return Module::factory()->for($course)->create();
    }

    public function test_an_oversized_file_is_rejected_with_a_clear_message(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $module = $this->ownedModule($instructor);

        // 30MB exceeds the 25MB lesson-media ceiling.
        $file = UploadedFile::fake()->create('huge.pdf', 30 * 1024, 'application/pdf');

        $this->actingAs($instructor)
            ->postJson(route('lessons.store', [$module->course, $module]), [
                'title' => 'Big PDF',
                'type' => LessonType::Pdf->value,
                'file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, $module->lessons()->count());
    }

    public function test_a_disallowed_file_type_is_rejected(): void
    {
        Storage::fake('private');
        $instructor = $this->userWithRole(Role::Instructor->value);
        $module = $this->ownedModule($instructor);

        // A PNG is not an allowed lesson_media type; the service rejects it.
        $file = UploadedFile::fake()->create('cover.png', 50, 'image/png');

        $this->actingAs($instructor)
            ->postJson(route('lessons.store', [$module->course, $module]), [
                'title' => 'Wrong type',
                'type' => LessonType::Document->value,
                'file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_a_valid_file_lesson_is_stored_privately(): void
    {
        Storage::fake('private');
        $instructor = $this->userWithRole(Role::Instructor->value);
        $module = $this->ownedModule($instructor);

        $file = UploadedFile::fake()->create('notes.pdf', 200, 'application/pdf');

        $this->actingAs($instructor)
            ->postJson(route('lessons.store', [$module->course, $module]), [
                'title' => 'Lecture notes',
                'type' => LessonType::Pdf->value,
                'file' => $file,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $lesson = $module->lessons()->firstOrFail();
        $media = $lesson->firstMediaFor(MediaPurpose::LessonMedia);

        $this->assertNotNull($media, 'The uploaded file is attached as private lesson media.');
        $this->assertSame('private', $media->disk);
        $this->assertNull($media->url, 'A private file never gets a public URL.');
        Storage::disk('private')->assertExists($media->path);
    }
}
