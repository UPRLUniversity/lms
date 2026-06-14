<?php

namespace Tests\Feature\Media;

use App\Enums\MediaPurpose;
use App\Models\User;
use App\Services\Media\MediaUploadService;
use App\Services\Media\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, int, string, bool}>
     */
    public static function uploads(): array
    {
        return [
            // purpose, sizeKb, mime, expectValid
            'avatar: valid png' => [MediaPurpose::Avatars->value, 100, 'image/png', true],
            'avatar: oversize' => [MediaPurpose::Avatars->value, 3000, 'image/png', false],
            'avatar: wrong mime (pdf)' => [MediaPurpose::Avatars->value, 100, 'application/pdf', false],
            'course cover: valid jpg' => [MediaPurpose::CourseCovers->value, 200, 'image/jpeg', true],
            'lesson image: valid gif' => [MediaPurpose::LessonImages->value, 200, 'image/gif', true],
            'signature: wrong mime (jpg)' => [MediaPurpose::Signatures->value, 50, 'image/jpeg', false],
            'submission: valid pdf' => [MediaPurpose::Submissions->value, 200, 'application/pdf', true],
            'submission: wrong mime (image)' => [MediaPurpose::Submissions->value, 200, 'image/jpeg', false],
            'submission: oversize pdf' => [MediaPurpose::Submissions->value, 30000, 'application/pdf', false],
            'certificate: valid pdf' => [MediaPurpose::Certificates->value, 100, 'application/pdf', true],
            'certificate: wrong mime' => [MediaPurpose::Certificates->value, 100, 'application/zip', false],
        ];
    }

    #[DataProvider('uploads')]
    public function test_validation_enforced_per_purpose(string $purposeValue, int $sizeKb, string $mime, bool $expectValid): void
    {
        Storage::fake('public');
        Storage::fake('private');
        $purpose = MediaPurpose::from($purposeValue);
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $file = UploadedFile::fake()->create('upload', $sizeKb, $mime);

        $store = fn () => $purpose->isPublic()
            ? app(MediaUploadService::class)->upload($file, $purpose, $owner)
            : app(PrivateFileService::class)->store($file, $purpose, $owner);

        if ($expectValid) {
            $media = $store();
            $this->assertDatabaseHas('media', ['id' => $media->id, 'purpose' => $purposeValue]);
        } else {
            $this->expectException(ValidationException::class);
            $store();
        }
    }
}
