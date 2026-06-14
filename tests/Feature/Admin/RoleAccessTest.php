<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_the_user_list(): void
    {
        $user = $this->userWithRole(Role::SuperAdmin->value);

        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
    }

    public function test_admin_can_view_the_user_list(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
    }

    public function test_instructor_is_denied_the_admin_area(): void
    {
        $user = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_student_hitting_an_admin_route_gets_the_branded_403(): void
    {
        $user = $this->userWithRole(Role::Student->value);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
        $response->assertSee('403');           // branded error page
        $response->assertSee('Back to safety');
    }

    public function test_auditor_can_view_the_list_but_every_write_is_rejected(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $target = User::factory()->create();

        // Read access is granted...
        $this->actingAs($auditor)->get(route('admin.users.index'))->assertOk();

        // ...but the create screen and every mutation are forbidden.
        $this->actingAs($auditor)->get(route('admin.users.create'))->assertForbidden();

        $this->actingAs($auditor)->post(route('admin.users.store'), [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'role' => Role::Student->value,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertForbidden();

        $this->actingAs($auditor)->patch(route('admin.users.status', $target), [
            'is_active' => 0,
        ])->assertForbidden();

        $this->assertTrue($target->fresh()->is_active, 'Auditor must not be able to deactivate anyone.');
    }

    public function test_auditor_user_list_hides_mutating_controls(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        User::factory()->create(['name' => 'Visible Person']);

        $response = $this->actingAs($auditor)->get(route('admin.users.index'));

        $response->assertSee('Visible Person');
        $response->assertDontSee('Deactivate');   // no action buttons for read-only role
        $response->assertDontSee('New user');
    }
}
