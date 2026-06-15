<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views render @vite(...) tags; tests must not depend on a built manifest.
        $this->withoutVite();

        // The array cache persists within a test process; clear spatie's permission
        // cache each test so a fresh (RefreshDatabase) schema isn't shadowed by it.
        if (app()->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Seed the role/permission matrix and return a fresh user holding $role.
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
