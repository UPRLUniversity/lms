<?php

namespace Database\Seeders;

use App\Enums\CertificateLayout;
use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

/**
 * The two shipped certificate designs, seeded EARLY (right after roles) — deliberately
 * before any course/enrolment/progress seeding runs, so every genuine course completion
 * elsewhere in the demo (ProgressSeeder's finished PRL101 students, GradeScaleSeeder's
 * completer) issues a REAL certificate through the normal CourseCompleted pipeline,
 * rather than needing to be faked after the fact.
 */
class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $classic = CertificateTemplate::updateOrCreate(['name' => 'Classic'], [
            'is_default' => true,
            'layout' => CertificateLayout::Classic->value,
            'config' => [
                'signatory_one' => [
                    'name' => 'Prof. Adaeze Nwosu',
                    'title' => 'Vice-Chancellor',
                    'signature_media_id' => null,
                ],
                'signatory_two' => [
                    'name' => 'Dr. Femi Balogun',
                    'title' => 'Registrar',
                    'signature_media_id' => null,
                ],
                'accent_color' => null,
                'show_grade' => true,
            ],
        ]);

        CertificateTemplate::updateOrCreate(['name' => 'Modern'], [
            'is_default' => false,
            'layout' => CertificateLayout::Modern->value,
            'config' => [
                'signatory_one' => [
                    'name' => 'Prof. Adaeze Nwosu',
                    'title' => 'Vice-Chancellor',
                    'signature_media_id' => null,
                ],
                'signatory_two' => null,
                'accent_color' => null,
                'show_grade' => true,
            ],
        ]);

        CertificateTemplate::query()->where('id', '!=', $classic->id)->update(['is_default' => false]);
    }
}
