<?php

namespace Tests\Feature;

use App\Enums\MediaPurpose;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_extended_profile_fields_and_digest_preference_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+234 800 000 0000',
            'title' => 'Senior Lecturer',
            'bio' => 'Teaches public relations.',
            'email_digest' => '1',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('+234 800 000 0000', $user->phone);
        $this->assertSame('Senior Lecturer', $user->title);
        $this->assertSame('Teaches public relations.', $user->bio);
        $this->assertTrue($user->wantsEmailDigest());
    }

    public function test_avatar_can_be_uploaded_and_replaced_through_the_media_service(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $this->imageUpload('me.png'),
        ])->assertRedirect('/profile');

        $first = $user->fresh()->avatar();
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first->path);

        // Replacing the avatar removes the previous file and keeps a single record.
        $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $this->imageUpload('new.png'),
        ])->assertRedirect('/profile');

        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame(1, $user->fresh()->media()->where('purpose', MediaPurpose::Avatars->value)->count());
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar());
    }

    /**
     * A real PNG upload from a committed fixture, so the suite doesn't need GD.
     */
    private function imageUpload(string $name = 'avatar.png'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl').'.png';
        copy(base_path('tests/Fixtures/pixel.png'), $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }
}
