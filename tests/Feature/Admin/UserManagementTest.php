<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Lecturer',
            'email' => 'lecturer@uprl.test',
            'role' => Role::Instructor->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'lecturer@uprl.test')->firstOrFail();
        $this->assertTrue($created->hasRole(Role::Instructor->value));
        $this->assertTrue($created->is_active);
        $this->assertNotNull($created->email_verified_at, 'Admin-created users are pre-verified.');
    }

    public function test_admin_cannot_grant_the_admin_role(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->from(route('admin.users.create'))
            ->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Sneaky Admin',
                'email' => 'sneaky@uprl.test',
                'role' => Role::Admin->value,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@uprl.test']);
    }

    public function test_super_admin_can_grant_the_admin_role(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'Real Admin',
            'email' => 'realadmin@uprl.test',
            'role' => Role::Admin->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertTrue(
            User::where('email', 'realadmin@uprl.test')->firstOrFail()->hasRole(Role::Admin->value),
        );
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $target), ['is_active' => 0])
            ->assertRedirect(route('admin.users.index'));
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $target), ['is_active' => 1])
            ->assertRedirect(route('admin.users.index'));
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $admin), ['is_active' => 0])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_a_super_admin(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::SuperAdmin->value);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $superAdmin), ['is_active' => 0])
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->is_active);
    }

    public function test_super_admin_cannot_deactivate_themselves(): void
    {
        $superAdmin = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($superAdmin)
            ->patch(route('admin.users.status', $superAdmin), ['is_active' => 0])
            ->assertForbidden();

        $this->assertTrue($superAdmin->fresh()->is_active, 'A super-admin cannot lock themselves out.');
    }

    public function test_a_user_cannot_change_their_own_role(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => Role::Student->value,
        ])->assertRedirect(route('admin.users.index'));

        $fresh = $admin->fresh();
        $this->assertTrue($fresh->hasRole(Role::Admin->value), 'Own role is unchanged.');
        $this->assertFalse($fresh->hasRole(Role::Student->value), 'Self-demotion is ignored.');
    }

    public function test_user_list_can_be_searched_and_filtered_by_role(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $alice = User::factory()->create(['name' => 'Alice Searchable', 'email' => 'alice@uprl.test']);
        $alice->assignRole(Role::Instructor->value);
        $bob = User::factory()->create(['name' => 'Bob Hidden', 'email' => 'bob@uprl.test']);
        $bob->assignRole(Role::Student->value);

        // Search by name.
        $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Searchable']))
            ->assertSee('Alice Searchable')
            ->assertDontSee('Bob Hidden');

        // Filter by role.
        $this->actingAs($admin)->get(route('admin.users.index', ['role' => Role::Instructor->value]))
            ->assertSee('Alice Searchable')
            ->assertDontSee('Bob Hidden');
    }
}
