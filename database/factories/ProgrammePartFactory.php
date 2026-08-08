<?php

namespace Database\Factories;

use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgrammePart>
 */
class ProgrammePartFactory extends Factory
{
    protected $model = ProgrammePart::class;

    public function definition(): array
    {
        $name = 'Part '.fake()->unique()->randomElement(['I', 'II', 'III', 'IV', 'V', 'VI']);

        return [
            'programme_id' => Programme::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(10),
            'credit_target' => 24,
            'unlock_credits' => null,
            'position' => 0,
        ];
    }

    public function named(string $name, int $position = 0): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => $position,
        ]);
    }

    /**
     * A part that states no credit target — most of them, in the real curriculum. Such a
     * part is judged on the compulsory bar alone.
     */
    public function withoutCreditTarget(): static
    {
        return $this->state(fn () => ['credit_target' => null, 'unlock_credits' => null]);
    }
}
