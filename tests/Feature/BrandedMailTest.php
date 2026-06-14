<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_email_uses_the_uprl_brand_theme(): void
    {
        $invitation = UserInvitation::factory()->create([
            'name' => 'Ada Lovelace',
            'role' => Role::Instructor->value,
        ]);

        $html = (string) (new UserInvitationNotification($invitation, 'raw-token'))
            ->toMail($invitation)
            ->render();

        // Brand crimson (inlined from the uprl theme) + the motto in the footer.
        $this->assertStringContainsStringIgnoringCase('c8102e', $html);
        $this->assertStringContainsString(config('brand.motto'), $html);
        $this->assertStringContainsString('Accept invitation', $html);
        $this->assertStringContainsString('Ada Lovelace', $html);
    }

    public function test_verification_email_is_branded_and_friendly(): void
    {
        $user = User::factory()->unverified()->create();

        $html = (string) (new VerifyEmail)->toMail($user)->render();

        $this->assertStringContainsStringIgnoringCase('c8102e', $html);
        $this->assertStringContainsString(config('brand.motto'), $html);
        $this->assertStringContainsString('Verify email address', $html);
        $this->assertStringContainsString('Welcome to '.config('brand.short'), $html);
    }

    public function test_password_reset_email_is_branded(): void
    {
        $user = User::factory()->create();

        $html = (string) (new ResetPassword('token-123'))->toMail($user)->render();

        $this->assertStringContainsStringIgnoringCase('c8102e', $html);
        $this->assertStringContainsString('Reset password', $html);
        $this->assertStringContainsString(config('brand.motto'), $html);
    }

    /**
     * @testWith ["invitation"]
     *           ["verify"]
     *           ["reset"]
     */
    public function test_local_mail_preview_routes_render(string $type): void
    {
        $this->get("/mail-preview/{$type}")
            ->assertOk()
            ->assertSee(config('brand.motto'));
    }
}
