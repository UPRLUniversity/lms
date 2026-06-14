<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use App\Services\InvitationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_user_by_email(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'name' => 'Invited Instructor',
            'email' => 'invitee@uprl.test',
            'role' => Role::Instructor->value,
        ]);

        $response->assertRedirect(route('admin.invitations.index'));

        $invitation = UserInvitation::where('email', 'invitee@uprl.test')->firstOrFail();
        $this->assertSame(Role::Instructor, $invitation->role);
        $this->assertTrue($invitation->isPending());
        // The raw token is never stored — only its hash.
        $this->assertSame(64, strlen($invitation->token));

        Notification::assertSentTo($invitation, UserInvitationNotification::class);
    }

    public function test_admin_cannot_invite_an_admin_but_super_admin_can(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'name' => 'X', 'email' => 'x@uprl.test', 'role' => Role::Admin->value,
        ])->assertSessionHasErrors('role');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->post(route('admin.invitations.store'), [
            'name' => 'Y', 'email' => 'y@uprl.test', 'role' => Role::Admin->value,
        ])->assertRedirect(route('admin.invitations.index'));

        $this->assertDatabaseHas('user_invitations', ['email' => 'y@uprl.test', 'role' => Role::Admin->value]);
    }

    public function test_invitee_can_accept_and_set_a_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $service = app(InvitationService::class);

        // Capture the raw token by issuing through the service.
        $raw = null;
        Notification::fake();
        $invitation = $service->invite('Pat Lecturer', 'pat@uprl.test', Role::Instructor);
        Notification::assertSentTo($invitation, UserInvitationNotification::class, function ($notification) use (&$raw) {
            $raw = $this->readToken($notification);

            return true;
        });

        $url = $this->acceptUrl($invitation, $raw);

        $this->get($url)->assertOk()->assertSee('Set your password');

        $response = $this->post(route('invitations.accept.store', $invitation), [
            'token' => $raw,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'pat@uprl.test')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Instructor->value));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->is_active);
        $this->assertTrue($invitation->fresh()->isAccepted());
    }

    public function test_an_accepted_invitation_link_cannot_be_reused(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $service = app(InvitationService::class);

        Notification::fake();
        $invitation = $service->invite('One Use', 'oneuse@uprl.test', Role::Student);
        $raw = null;
        Notification::assertSentTo($invitation, UserInvitationNotification::class, function ($notification) use (&$raw) {
            $raw = $this->readToken($notification);

            return true;
        });

        // First acceptance succeeds.
        $this->post(route('invitations.accept.store', $invitation), [
            'token' => $raw,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout');

        // Second attempt with the same token is rejected.
        $this->post(route('invitations.accept.store', $invitation), [
            'token' => $raw,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertRedirect(route('login'));

        $this->assertSame(1, User::where('email', 'oneuse@uprl.test')->count());
    }

    public function test_an_expired_invitation_link_is_invalid(): void
    {
        $invitation = UserInvitation::factory()->expired()->create();

        // The signed link itself is expired, so the route guard 403s.
        $url = $this->acceptUrl($invitation, 'whatever', expired: true);
        $this->get($url)->assertForbidden();

        // And even a forged unsigned POST cannot accept it.
        $this->post(route('invitations.accept.store', $invitation), [
            'token' => 'whatever',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => $invitation->email]);
    }

    public function test_admin_can_resend_a_pending_invitation(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::Admin->value);
        $invitation = UserInvitation::factory()->create();
        $originalToken = $invitation->token;

        $this->actingAs($admin)
            ->post(route('admin.invitations.resend', $invitation))
            ->assertRedirect();

        // Re-sending rotates the token and pushes the expiry out.
        $this->assertNotSame($originalToken, $invitation->fresh()->token);
        Notification::assertSentTo($invitation, UserInvitationNotification::class);
    }

    public function test_resend_and_revoke_via_ajax_return_json(): void
    {
        Notification::fake();
        $admin = $this->userWithRole(Role::Admin->value);
        $invitation = UserInvitation::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.invitations.resend', $invitation))
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->actingAs($admin)
            ->deleteJson(route('admin.invitations.destroy', $invitation))
            ->assertOk()
            ->assertJson(['message' => 'Invitation revoked.']);

        $this->assertDatabaseMissing('user_invitations', ['id' => $invitation->id]);
    }

    public function test_invitations_ajax_request_returns_only_the_table_partial(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        UserInvitation::factory()->create(['email' => 'partial@uprl.test']);

        $response = $this->actingAs($admin)->getJson(route('admin.invitations.index'));

        $response->assertOk();
        $response->assertSee('partial@uprl.test');
        $response->assertDontSee('<html', false);          // no layout chrome
        $response->assertDontSee('Send an invitation', false); // no invite form
    }

    /**
     * Read the raw (un-hashed) token off a fired notification. It's a protected
     * property, so a closure bound to the notification reads it cleanly.
     */
    protected function readToken(UserInvitationNotification $notification): string
    {
        return (fn (): string => $this->rawToken)->call($notification);
    }

    protected function acceptUrl(UserInvitation $invitation, string $token, bool $expired = false): string
    {
        return URL::temporarySignedRoute(
            'invitations.accept',
            $expired ? now()->subDay() : now()->addDay(),
            ['invitation' => $invitation->id, 'token' => $token],
        );
    }
}
