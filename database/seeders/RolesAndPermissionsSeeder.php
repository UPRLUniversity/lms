<?php

namespace Database\Seeders;

use App\Enums\Permission as Perm;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for the access matrix. Idempotent (firstOrCreate +
 * syncPermissions) so it can run on every `db:seed` without drift. The auditor is
 * read-only by construction: it receives only the ".view" permissions.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Every permission, web guard.
        foreach (Perm::values() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $matrix = $this->matrix();

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::firstOrCreate(['name' => $roleEnum->value, 'guard_name' => 'web']);
            $role->syncPermissions($matrix[$roleEnum->value]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * role name => list of permission names.
     *
     * @return array<string, array<int, string>>
     */
    protected function matrix(): array
    {
        // super-admin bypasses every check via a Gate::before (see AppServiceProvider),
        // but is still granted the full set explicitly for clarity and tooling.
        $all = Perm::values();

        $admin = [
            Perm::UsersView->value,
            Perm::UsersManage->value,
            Perm::RolesManage->value,
            Perm::CoursesView->value,
            Perm::CoursesCreate->value,
            Perm::CoursesManage->value,
            Perm::EnrollmentsView->value,
            Perm::EnrollmentsApprove->value,
            Perm::AssessmentsView->value,
            Perm::AssessmentsGrade->value,
            Perm::ReportsView->value,
        ];

        $instructor = [
            Perm::CoursesView->value,
            Perm::CoursesCreate->value,
            Perm::CoursesManage->value,
            Perm::EnrollmentsView->value,
            Perm::EnrollmentsApprove->value,
            Perm::AssessmentsView->value,
            Perm::AssessmentsGrade->value,
        ];

        $student = [
            Perm::CoursesView->value,
        ];

        return [
            RoleEnum::SuperAdmin->value => $all,
            RoleEnum::Admin->value => $admin,
            RoleEnum::Instructor->value => $instructor,
            RoleEnum::Student->value => $student,
            // Read-only observer: every ".view" permission, nothing that mutates.
            RoleEnum::Auditor->value => Perm::readOnly(),
        ];
    }
}
