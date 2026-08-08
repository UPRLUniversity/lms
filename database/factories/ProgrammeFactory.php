<?php

namespace Database\Factories;

use App\Enums\ProgressionRule;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    protected $model = Programme::class;

    public function definition(): array
    {
        $name = 'Professional '.Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'code' => Str::upper(Str::random(4)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'tagline' => fake()->sentence(8),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'registration_fee' => 20000,
            'administration_fee' => 25000,
            'per_paper_fee' => 7000,
            'position' => 0,
            'is_active' => true,
            'progression_rule' => ProgressionRule::Open->value,
        ];
    }

    /**
     * Parts must be worked through in order — the state every progression test needs,
     * and the one a factory must never produce by accident.
     */
    public function sequential(): static
    {
        return $this->state(fn () => ['progression_rule' => ProgressionRule::Sequential->value]);
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'registration_fee' => 0,
            'administration_fee' => 0,
            'per_paper_fee' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
