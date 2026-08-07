<?php

namespace Tests\Feature\Certificates;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The certificate-template signature upload. This endpoint had no coverage at all, which
 * is how it shipped rejecting the two commonest inputs (a JPG scan, an over-1MB file)
 * behind a single unhelpful toast. These tests pin the happy path AND, just as
 * importantly, that each refusal comes back with a message that says what to do about it.
 */
class SignatureUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_png_signature_uploads_and_returns_a_media_reference(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->postJson(
            route('admin.certificate-templates.signature-upload'),
            ['file' => UploadedFile::fake()->image('signature.png', 600, 180)],
        );

        $response->assertOk()
            ->assertJsonStructure(['id', 'url']);

        $this->assertDatabaseHas('media', [
            'id' => $response->json('id'),
            'purpose' => 'signatures',
            'original_name' => 'signature.png',
        ]);
    }

    public function test_a_jpeg_is_refused_with_a_message_naming_the_accepted_formats(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->postJson(
            route('admin.certificate-templates.signature-upload'),
            ['file' => UploadedFile::fake()->image('scan.jpg', 600, 180)],
        );

        $response->assertStatus(422);

        // The point of the fix: the reason is specific and actionable, not "upload failed".
        $this->assertStringContainsString('PNG or WebP', $response->json('errors.file.0'));
    }

    public function test_an_oversized_image_is_refused_with_the_size_limit_stated(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->postJson(
            route('admin.certificate-templates.signature-upload'),
            ['file' => UploadedFile::fake()->create('big.png', 1500, 'image/png')],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('1MB', $response->json('errors.file.0'));
    }

    public function test_a_student_cannot_upload_a_signature(): void
    {
        Storage::fake('public');
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->postJson(
            route('admin.certificate-templates.signature-upload'),
            ['file' => UploadedFile::fake()->image('signature.png', 600, 180)],
        )->assertForbidden();
    }
}
