<?php

namespace Tests\Feature;

use App\Enums\MediaPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorUploadTest extends TestCase
{
    use RefreshDatabase;

    private function png(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl').'.png';
        copy(base_path('tests/Fixtures/pixel.png'), $tmp);

        return new UploadedFile($tmp, 'inline.png', 'image/png', null, true);
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('editor.upload'), ['file' => $this->png()])
            ->assertRedirect('/login');
    }

    public function test_authenticated_upload_returns_tinymce_location_json(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('editor.upload'), ['file' => $this->png()]);

        $response->assertOk()->assertJsonStructure(['location']);
        $this->assertNotEmpty($response->json('location'));

        $this->assertDatabaseHas('media', [
            'purpose' => MediaPurpose::EditorUploads->value,
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_non_image_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('editor.upload'), ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertStatus(422);
    }
}
