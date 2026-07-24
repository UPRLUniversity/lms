<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'serial' => 'UPRL-'.now()->year.'-'.Str::upper(Str::random(6)),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'certificate_template_id' => CertificateTemplate::factory(),
            'snapshot' => [
                'layout' => 'classic',
                'accent_color' => '#C9A227',
                'show_grade' => true,
                'signatories' => [
                    ['name' => 'Prof. Adaeze Nwosu', 'title' => 'Vice-Chancellor', 'signature_media_id' => null],
                ],
                'student_name' => $this->faker->name(),
                'course_title' => $this->faker->sentence(4),
                'completion_date' => now()->isoFormat('D MMMM YYYY'),
                'grade' => null,
            ],
            'issued_at' => now(),
            'rendered_at' => now(),
            'revoked_at' => null,
            'revocation_reason' => null,
        ];
    }

    public function revoked(string $reason = 'Academic integrity investigation.'): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['rendered_at' => null]);
    }

    public function withGrade(string $label = 'A', float $point = 4.5, float $limit = 5.0, float $percent = 88): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot' => array_merge($attributes['snapshot'], [
                'grade' => [
                    'final_percent' => $percent,
                    'grade_label' => $label,
                    'grade_point' => $point,
                    'scale_name' => 'NUC Standard (5.0)',
                    'display_mode' => 'both',
                    'separator' => '/',
                    'show_scale_limit' => true,
                    'scale_limit' => $limit,
                ],
            ]),
        ]);
    }
}
