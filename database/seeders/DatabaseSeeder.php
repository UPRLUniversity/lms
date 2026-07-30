<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\Support\Nigeria;
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

        // Certificate templates, seeded EARLY (before any course completes) so every
        // genuine completion below issues a real certificate through the normal
        // CourseCompleted pipeline — see CertificateTemplateSeeder's own note.
        $this->call(CertificateTemplateSeeder::class);

        // 1 super-admin.
        $this->make('Olusola Adeyemi', 'superadmin@uprl.test', Role::SuperAdmin);

        // 2 admins.
        $this->make('Amaka Okoye', 'admin1@uprl.test', Role::Admin);
        $this->make('Bello Sanusi', 'admin2@uprl.test', Role::Admin);

        // 4 instructors — fixed, recognisable Nigerian names (they lead courses, author
        // announcements and grade, so a stable identity reads better across the demo).
        $instructorNames = ['Adebayo Ogunleye', 'Chidinma Okafor', 'Ibrahim Danjuma', 'Folake Adeniyi'];
        foreach ($instructorNames as $i => $name) {
            $this->make($name, 'instructor'.($i + 1).'@uprl.test', Role::Instructor, [
                'title' => Nigeria::academicTitle(),
            ]);
        }

        // 25 students — authentic Nigerian names across the three major ethnic groups.
        foreach (range(1, 25) as $i) {
            $this->make(Nigeria::fullName(), "student{$i}@uprl.test", Role::Student);
        }

        // 1 read-only auditor.
        $this->make('Grace Eze', 'auditor@uprl.test', Role::Auditor);

        // Deactivate the last student so the login-deactivation gate is
        // demonstrable without inflating the roster beyond the spec's counts.
        User::where('email', 'student25@uprl.test')->update(['is_active' => false]);

        // Academic structure + a clickable course catalogue.
        $this->call(CourseSeeder::class);

        // The NIPR qualification structure (CPR / DPR / Professional Variant), its parts
        // and the full published paper list. Runs after CourseSeeder because these
        // courses join the faculty/department hierarchy it builds, and because it also
        // places the eight hand-written demo courses into parts.
        $this->call(ProgrammeSeeder::class);

        // Prices the NIPR papers, installs the payment methods, and places real demo
        // orders through CheckoutService. Runs after ProgrammeSeeder because a course's
        // price is inherited from its primary programme's per-paper fee.
        $this->call(CommerceSeeder::class);

        // A realistic spread of enrolments across every mode and status.
        $this->call(EnrollmentSeeder::class);

        // Lesson progress: mid-course resume, finished courses, a sequential demo.
        $this->call(ProgressSeeder::class);

        // Question bank + assessments (pre/post pair, timed exam, pooled exam, essay) with
        // a spread of attempts incl. one awaiting grading and a pre→post knowledge gain.
        $this->call(AssessmentSeeder::class);

        // Rubric + assignments with submissions in mixed states (graded via rubric,
        // returned + resubmitted, late in the queue).
        $this->call(AssignmentSeeder::class);

        // Grade scales (NUC Standard default + 4.0 Scale) and a believable gradebook on
        // the same course: one genuinely completed student (real CourseGradeRecord via
        // the completion pipeline) and one left "Provisional" pending a graded hand-in.
        $this->call(GradeScaleSeeder::class);

        // Certificates (Section 7): tops up the registry if the natural pipeline above
        // produced fewer than three, and revokes one so every state is demonstrable.
        $this->call(CertificateSeeder::class);

        // Communication (Section 9): a lived-in course forum (pinned/answered/locked
        // threads with replies) plus direct + course-group conversations for the demo.
        $this->call(CommunicationSeeder::class);

        // Notifications (Section 8): most of the catalogue already fired naturally above
        // (certificate issuance); this tops up the rest against the same real, seeded
        // data so the bell and /notifications page are a convincing demo immediately.
        $this->call(NotificationSeeder::class);

        // Reporting (Section 10): recent sign-in times + back-dated enrolments across the
        // year so the dashboards' "active users", enrolment trend and top courses read
        // like a lived-in platform. Real rows the reports then aggregate.
        $this->call(ReportingDemoSeeder::class);
    }

    /**
     * Create-or-update a verified, active user and (re)assign its single role.
     * Password is passed plain; the model's 'hashed' cast hashes it once. Every demo
     * account carries a Nigerian +234 phone; extra attributes (e.g. an instructor's
     * academic title) merge in on top.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function make(string $name, string $email, Role $role, array $attributes = []): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            array_merge([
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
                'phone' => Nigeria::phone(),
            ], $attributes),
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
