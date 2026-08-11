<?php

namespace Database\Seeders;

use App\Enums\CertificateLayout;
use App\Enums\GradeDisplayMode;
use App\Enums\GradeScaleStatus;
use App\Enums\PaymentEnvironment;
use App\Enums\Role;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\GradeScale;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Spatie\Activitylog\Facades\Activity;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        Activity::withoutLogs(function () {
            $this->seedDefaults();
        });
    }

    private function seedDefaults(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $this->seedAccessAccounts();
        $this->seedAcademicStructure();

        // Real NIPR programme structure, parts, papers, and placements.
        $this->call(ProgrammeSeeder::class);

        $this->priceProgrammeCourses();
        $this->seedGradeScales();
        $this->seedCertificateTemplate();
        $this->seedPaymentMethods();
    }

    private function seedAccessAccounts(): void
    {
        $this->user('Olasquare Consults', 'olasquareconsults@gmail.com', 'olayiwola', Role::SuperAdmin);
        $this->user('UPRL NIPR Admin', 'uprlnipr@gmail.com', 'password', Role::Admin);
    }

    private function user(string $name, string $email, string $password, Role $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->syncRoles([$role->value]);

        return $user;
    }

    private function seedAcademicStructure(): void
    {
        foreach ($this->structure() as $facultyName => $departmentNames) {
            $faculty = $this->faculty($facultyName);

            foreach ($departmentNames as $departmentName) {
                $this->department($faculty, $departmentName);
            }
        }
    }

    private function faculty(string $name): Faculty
    {
        $faculty = Faculty::firstOrNew(['name' => $name]);

        if (! $faculty->exists) {
            $faculty->slug = Slug::unique(Faculty::class, $name);
        }

        if (blank($faculty->description)) {
            $faculty->description = 'Part of the '.config('brand.university').'.';
        }

        $faculty->save();

        return $faculty;
    }

    private function department(Faculty $faculty, string $name): Department
    {
        $department = Department::firstOrNew(['name' => $name]);

        if (! $department->exists) {
            $department->slug = Slug::unique(Department::class, $name);
        }

        $department->faculty()->associate($faculty);
        $department->save();

        return $department;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function structure(): array
    {
        return [
            'Faculty of Communication & Media Studies' => [
                'Department of Public Relations',
                'Department of Journalism & Media',
                'Department of Strategic Communication',
            ],
            'College of Leadership & Development Studies' => [
                'Department of Organisational Leadership',
                'Department of Development Studies',
                'Department of Public Administration',
            ],
        ];
    }

    private function priceProgrammeCourses(): void
    {
        $paidProgrammes = Programme::query()->where('per_paper_fee', '>', 0)->pluck('id');

        if ($paidProgrammes->isEmpty()) {
            return;
        }

        Course::query()
            ->whereHas('programmeParts', fn ($q) => $q
                ->whereIn('programme_parts.programme_id', $paidProgrammes)
                ->where('course_programme_part.is_primary', true))
            ->whereNull('price_override')
            ->update(['is_free' => false]);
    }

    private function seedGradeScales(): void
    {
        $hasDefault = GradeScale::query()->where('is_default', true)->exists();

        $nuc = $this->gradeScale('NUC Standard (5.0)', 5.00, ! $hasDefault, [
            ['label' => 'A', 'grade_point' => 5.00, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 4.00, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 3.00, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
            ['label' => 'D', 'grade_point' => 2.00, 'is_pass' => true, 'min_percent' => 45, 'max_percent' => 49, 'color' => 'neutral'],
            ['label' => 'E', 'grade_point' => 1.00, 'is_pass' => true, 'min_percent' => 40, 'max_percent' => 44, 'color' => 'neutral'],
            ['label' => 'F', 'grade_point' => 0.00, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 39, 'color' => 'crimson'],
        ]);

        $this->gradeScale('4.0 Scale', 4.00, false, [
            ['label' => 'A', 'grade_point' => 4.00, 'is_pass' => true, 'min_percent' => 80, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 3.00, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 79, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 2.00, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'ink'],
            ['label' => 'D', 'grade_point' => 1.00, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'neutral'],
            ['label' => 'F', 'grade_point' => 0.00, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ]);

        if (! $hasDefault) {
            $nuc->forceFill(['is_default' => true])->save();
            GradeScale::query()->whereKeyNot($nuc->id)->update(['is_default' => false]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $bands
     */
    private function gradeScale(string $name, float $limit, bool $default, array $bands): GradeScale
    {
        $scale = GradeScale::firstOrCreate(
            ['name' => $name],
            [
                'scale_limit' => $limit,
                'is_default' => $default,
                'display_mode' => GradeDisplayMode::Both->value,
                'show_scale_limit' => true,
                'separator' => '/',
                'status' => GradeScaleStatus::Active->value,
            ],
        );

        if ($scale->bands()->doesntExist()) {
            foreach (array_values($bands) as $position => $band) {
                $scale->bands()->create($band + ['position' => $position]);
            }
        }

        return $scale;
    }

    private function seedCertificateTemplate(): void
    {
        if (CertificateTemplate::query()->where('is_default', true)->exists()) {
            return;
        }

        $template = CertificateTemplate::firstOrCreate(
            ['name' => 'Default'],
            [
                'layout' => CertificateLayout::Classic->value,
                'config' => [
                    'signatory_one' => null,
                    'signatory_two' => null,
                    'accent_color' => null,
                    'show_grade' => true,
                ],
            ],
        );

        $template->forceFill(['is_default' => true])->save();
        CertificateTemplate::query()->whereKeyNot($template->id)->update(['is_default' => false]);
    }

    private function seedPaymentMethods(): void
    {
        PaymentMethod::firstOrCreate(
            ['key' => 'paystack'],
            [
                'label' => 'Paystack',
                'is_enabled' => false,
                'environment' => PaymentEnvironment::Test,
                'config' => config('commerce.drivers.paystack.config', ['public_key' => '', 'secret_key' => '']),
                'position' => 1,
            ],
        );

        PaymentMethod::firstOrCreate(
            ['key' => 'bank_transfer'],
            [
                'label' => 'Bank transfer',
                'is_enabled' => false,
                'environment' => PaymentEnvironment::Live,
                'config' => [],
                'instructions' => null,
                'position' => 2,
            ],
        );
    }
}
