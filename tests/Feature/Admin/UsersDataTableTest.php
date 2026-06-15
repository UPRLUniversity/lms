<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_request_returns_only_the_table_partial(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.users.index', ['search' => '']));

        $response->assertOk();
        // The partial has no <html>/layout chrome — just the table region.
        $response->assertDontSee('<html', false);
        $response->assertDontSee('id="users-results"', false);
        $response->assertSee('</table>', false);
    }

    public function test_full_page_renders_the_layout(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('id="users-results"', false);
    }

    public function test_results_can_be_sorted_by_name_ascending_and_descending(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        // Use names that sort distinctly and won't collide with the admin's faker name.
        User::factory()->create(['name' => 'Aaron First']);
        User::factory()->create(['name' => 'Zara Last']);

        $asc = $this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'asc']));
        $this->assertLessThan(
            strpos($asc->getContent(), 'Zara Last'),
            strpos($asc->getContent(), 'Aaron First'),
            'Ascending: Aaron should appear before Zara.',
        );

        $desc = $this->actingAs($admin)->get(route('admin.users.index', ['sort' => 'name', 'direction' => 'desc']));
        $this->assertLessThan(
            strpos($desc->getContent(), 'Aaron First'),
            strpos($desc->getContent(), 'Zara Last'),
            'Descending: Zara should appear before Aaron.',
        );
    }

    public function test_invalid_sort_column_falls_back_to_name(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        // A non-whitelisted column must not reach the query (no SQL error / injection).
        $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'password', 'direction' => 'drop']))
            ->assertOk();
    }

    public function test_deactivate_via_ajax_returns_json_message(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $target = User::factory()->create(['name' => 'Toggle Target']);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.users.status', $target), ['is_active' => 0]);

        $response->assertOk();
        $response->assertJson(['message' => 'Toggle Target was deactivated.']);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_status_route_still_redirects_for_a_non_ajax_request(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $target), ['is_active' => 0])
            ->assertRedirect(route('admin.users.index'));
    }
}
