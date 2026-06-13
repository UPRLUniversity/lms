<?php

namespace Tests\Feature\Media;

use App\Enums\MediaPurpose;
use App\Models\User;
use App\Services\Media\PrivateFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_store_keeps_file_off_public_url(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $media = app(PrivateFileService::class)->store(
            UploadedFile::fake()->create('submission.pdf', 200, 'application/pdf'),
            MediaPurpose::Submissions,
            $owner,
        );

        $this->assertSame('private', $media->visibility);
        $this->assertSame('private', $media->disk);
        $this->assertNull($media->url, 'Private files must never expose a public URL.');
        Storage::disk('private')->assertExists($media->path);
    }

    public function test_temporary_url_streams_the_file_and_rejects_tampering(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $media = app(PrivateFileService::class)->store(
            UploadedFile::fake()->create('submission.pdf', 120, 'application/pdf'),
            MediaPurpose::Submissions,
            $owner,
        );

        $url = app(PrivateFileService::class)->temporaryUrl($media, 15);
        $this->assertStringContainsString('/media/'.$media->id.'/temporary', $url);
        $this->assertStringContainsString('signature=', $url);

        $this->get($url)->assertOk();
        $this->get($url.'tampered')->assertForbidden();
    }

    public function test_gated_download_is_authorized_by_policy(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $media = app(PrivateFileService::class)->store(
            UploadedFile::fake()->create('submission.pdf', 120, 'application/pdf'),
            MediaPurpose::Submissions,
            $owner,
        );

        // Uploader may download.
        $this->actingAs($owner)->get(route('media.download', $media))->assertOk();

        // A different user may not.
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('media.download', $media))->assertForbidden();

        // Guests are bounced to login.
        auth()->logout();
        $this->get(route('media.download', $media))->assertRedirect('/login');
    }
}
