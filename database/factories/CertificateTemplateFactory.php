<?php

namespace Database\Factories;

use App\Enums\CertificateLayout;
use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' template',
            'is_default' => false,
            'layout' => CertificateLayout::Classic->value,
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
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function modern(): static
    {
        return $this->state(fn () => ['layout' => CertificateLayout::Modern->value]);
    }

    public function withoutGrade(): static
    {
        return $this->state(fn (array $attributes) => [
            'config' => array_merge($attributes['config'], ['show_grade' => false]),
        ]);
    }
}
