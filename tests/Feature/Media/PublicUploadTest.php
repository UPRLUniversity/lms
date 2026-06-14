<?php

namespace Tests\Feature\Media;

use App\Enums\MediaPurpose;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\LocalMediaService;
use App\Services\Media\MediaUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A real PNG upload built from a committed fixture, so tests don't depend on
     * the GD extension (the app reads dimensions via getimagesize()).
     */
    private function imageUpload(string $name = 'portrait.png'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl').'.png';
        copy(base_path('tests/Fixtures/pixel.png'), $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    public function test_service_binds_to_local_in_test_environment(): void
    {
        $this->assertInstanceOf(LocalMediaService::class, app(MediaUploadService::class));
    }

    public function test_public_upload_persists_media_with_metadata_and_attaches_owner(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $service = app(MediaUploadService::class);

        $media = $service->upload($this->imageUpload('portrait.png'), MediaPurpose::Avatars, $owner);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'purpose' => MediaPurpose::Avatars->value,
            'visibility' => 'public',
            'provider' => 'local',
            'disk' => 'public',
            'mediable_type' => $owner->getMorphClass(),
            'mediable_id' => $owner->id,
            'original_name' => 'portrait.png',
        ]);

        $this->assertNotNull($media->url);
        $this->assertSame('image/png', $media->mime);
        $this->assertGreaterThan(0, $media->size_bytes);
        $this->assertSame(1, $media->width);
        $this->assertSame(1, $media->height);

        Storage::disk('public')->assertExists($media->path);
        $this->assertTrue($owner->firstMediaFor(MediaPurpose::Avatars)->is($media));
    }

    public function test_destroy_removes_file_and_row(): void
    {
        Storage::fake('public');
        $service = app(MediaUploadService::class);
        $media = $service->upload($this->imageUpload('a.png'), MediaPurpose::LessonImages);

        $path = $media->path;
        Storage::disk('public')->assertExists($path);

        $service->destroy($media);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
