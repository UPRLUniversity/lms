<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An admin must not be able to reach above their own head.
 *
 * Every case here was reachable before the guards landed. The one that mattered most was
 * not the obvious one: the role field was already defended, the e-mail field was not, and
 * an e-mail change plus a password reset is a complete account takeover that never touches
 * a role at all.
 */
class SuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWithRole(Role::Admin->value);
        $this->super = $this->userWithRole(Role::SuperAdmin->value);
    }

    /*
    |--------------------------------------------------------------------------
    | The takeover route
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_cannot_change_a_super_admins_email_address(): void
    {
        // The whole attack in one request: point the address at an inbox you control,
        // then use the ordinary "forgot password" flow to walk in.
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->super), [
                'name' => $this->super->name,
                'email' => 'attacker@example.com',
                'role' => Role::SuperAdmin->value,
            ])
            ->assertForbidden();

        $this->assertNotSame('attacker@example.com', $this->super->fresh()->email);
    }

    public function test_an_admin_cannot_even_open_a_super_admins_edit_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->super))
            ->assertForbidden();
    }

    public function test_the_user_list_offers_an_admin_no_edit_button_against_a_super_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index'));

        $response->assertOk()
            ->assertDontSee(route('admin.users.edit', $this->super));

        // But the row is still actionable in the ways it should be: an admin has every
        // reason to message a super-admin, and that button sat behind the same gate.
        $response->assertSee(route('messages.start', $this->super), escape: false);
    }

    /*
    |--------------------------------------------------------------------------
    | Demotion, and the chain it opened
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_cannot_demote_a_super_admin(): void
    {
        // Student is a role an admin may hand out, which is exactly why asking only
        // "may you grant this role?" was not enough.
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->super), [
                'name' => $this->super->name,
                'email' => $this->super->email,
                'role' => Role::Student->value,
            ])
            ->assertForbidden();

        $this->assertTrue($this->super->fresh()->hasRole(Role::SuperAdmin->value));
    }

    public function test_an_admin_cannot_demote_a_super_admin_then_deactivate_them(): void
    {
        // The deactivate guard was always correct, and always bypassable: it read the
        // target's role at the moment of the click, so demoting first made the second
        // click legitimate.
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->super), [
            'name' => $this->super->name,
            'email' => $this->super->email,
            'role' => Role::Student->value,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $this->super), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($this->super->fresh()->is_active);
        $this->assertTrue($this->super->fresh()->hasRole(Role::SuperAdmin->value));
    }

    public function test_an_admin_cannot_strip_another_admins_privilege_either(): void
    {
        // Same rule, one rung down. Only a super-admin may mint an admin, so only a
        // super-admin may unmake one.
        $other = $this->userWithRole(Role::Admin->value);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $other), [
                'name' => $other->name,
                'email' => $other->email,
                'role' => Role::Student->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($other->fresh()->hasRole(Role::Admin->value));
    }

    /*
    |--------------------------------------------------------------------------
    | The last one standing
    |--------------------------------------------------------------------------
    */

    /**
     * The last-super-admin backstop, tested where it can actually be exercised.
     *
     * No HTTP request can currently reach it: getting past the policy onto a super-admin
     * means you are one yourself, and acting on your own account is refused separately,
     * so two of you are always active and the target is never the last. The guard is kept
     * anyway, and pinned here, because "unreachable" is a property of today's screens
     * rather than of the rule.
     */
    public function test_the_last_active_super_admin_recognises_itself(): void
    {
        $this->assertTrue($this->super->isLastActiveSuperAdmin());

        $second = $this->userWithRole(Role::SuperAdmin->value);
        $this->assertFalse($this->super->fresh()->isLastActiveSuperAdmin());

        // Deactivating the second puts the first back on its own: a super-admin who
        // cannot sign in is no cover for one who can.
        $second->update(['is_active' => false]);
        $this->assertTrue($this->super->fresh()->isLastActiveSuperAdmin());
        $this->assertFalse($second->fresh()->isLastActiveSuperAdmin());

        // And an ordinary account never counts, however many are about.
        $this->assertFalse($this->admin->isLastActiveSuperAdmin());
    }

    public function test_a_super_admin_can_be_demoted_while_another_active_one_remains(): void
    {
        // The guard protects the last one, not the office. With cover in place the
        // ordinary change must still go through, or the rule is just an obstruction.
        $acting = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($acting)
            ->put(route('admin.users.update', $this->super), [
                'name' => $this->super->name,
                'email' => $this->super->email,
                'role' => Role::Student->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->super->fresh()->hasRole(Role::Student->value));
    }

    /*
    |--------------------------------------------------------------------------
    | What must keep working
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_can_still_manage_ordinary_accounts(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $student), [
                'name' => 'Renamed Student',
                'email' => 'renamed@uprl.test',
                'role' => Role::Instructor->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('renamed@uprl.test', $student->email);
        $this->assertTrue($student->hasRole(Role::Instructor->value));

        $this->actingAs($this->admin)
            ->patch(route('admin.users.status', $student), ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($student->fresh()->is_active);
    }

    public function test_a_super_admin_can_still_edit_their_own_account(): void
    {
        $this->actingAs($this->super)
            ->put(route('admin.users.update', $this->super), [
                'name' => 'Renamed Super',
                'email' => 'renamed-super@uprl.test',
                'role' => Role::SuperAdmin->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('renamed-super@uprl.test', $this->super->fresh()->email);
        $this->assertTrue($this->super->fresh()->hasRole(Role::SuperAdmin->value));
    }

    public function test_a_super_admin_can_still_edit_another_super_admin(): void
    {
        $acting = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($acting)
            ->put(route('admin.users.update', $this->super), [
                'name' => 'Peer Renamed',
                'email' => $this->super->email,
                'role' => Role::SuperAdmin->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Peer Renamed', $this->super->fresh()->name);
    }

    public function test_an_admin_can_still_edit_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => 'Renamed Admin',
                'email' => $this->admin->email,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Admin', $this->admin->fresh()->name);
        // Self role changes are ignored regardless of what is posted.
        $this->assertTrue($this->admin->fresh()->hasRole(Role::Admin->value));
    }
}
