<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a clickable demo: the full role matrix plus a realistic roster.
     * Idempotent — re-running won't collide on unique emails or duplicate roles.
     * Every account's password is "password" (see README for the credential table).
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // 1 super-admin.
        $this->make('Olusola Adeyemi', 'superadmin@uprl.test', Role::SuperAdmin);

        // 2 admins.
        $this->make('Amaka Okoye', 'admin1@uprl.test', Role::Admin);
        $this->make('Bello Sanusi', 'admin2@uprl.test', Role::Admin);

        // 4 instructors.
        foreach (range(1, 4) as $i) {
            $this->make(fake()->name(), "instructor{$i}@uprl.test", Role::Instructor);
        }

        // 25 students.
        foreach (range(1, 25) as $i) {
            $this->make(fake()->name(), "student{$i}@uprl.test", Role::Student);
        }

        // 1 read-only auditor.
        $this->make('Grace Eze', 'auditor@uprl.test', Role::Auditor);

        // Deactivate the last student so the login-deactivation gate is
        // demonstrable without inflating the roster beyond the spec's counts.
        User::where('email', 'student25@uprl.test')->update(['is_active' => false]);

        // Academic structure + a clickable course catalogue.
        $this->call(CourseSeeder::class);

        // A realistic spread of enrolments across every mode and status.
        $this->call(EnrollmentSeeder::class);

        // Lesson progress: mid-course resume, finished courses, a sequential demo.
        $this->call(ProgressSeeder::class);

        // Question bank + assessments (pre/post pair, timed exam, pooled exam, essay) with
        // a spread of attempts incl. one awaiting grading and a pre→post knowledge gain.
        $this->call(AssessmentSeeder::class);
    }

    /**
     * Create-or-update a verified, active user and (re)assign its single role.
     * Password is passed plain; the model's 'hashed' cast hashes it once.
     */
    protected function make(string $name, string $email, Role $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
            ],
        );

        // email_verified_at is guarded; set it through the model API so the
        // demo accounts can sign straight in without the verification gate.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->syncRoles([$role->value]);

        return $user;
    }
}
