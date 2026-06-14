<?php

namespace Database\Factories;

use App\Enums\LessonType;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => Str::title(fake()->words(4, true)),
            'type' => LessonType::Text->value,
            'content_text' => '<p>'.fake()->paragraph().'</p>',
            'video_url' => null,
            'video_provider' => null,
            'external_url' => null,
            'duration_minutes' => fake()->numberBetween(3, 45),
            'is_free_preview' => false,
            'position' => 0,
        ];
    }

    public function video(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): static
    {
        return $this->state(fn () => [
            'type' => LessonType::Video->value,
            'content_text' => null,
            'video_url' => $url,
            'video_provider' => str_contains($url, 'vimeo') ? 'vimeo' : 'youtube',
        ]);
    }

    public function externalLink(string $url = 'https://example.com/resource'): static
    {
        return $this->state(fn () => [
            'type' => LessonType::ExternalLink->value,
            'content_text' => null,
            'external_url' => $url,
        ]);
    }

    public function freePreview(): static
    {
        return $this->state(fn () => ['is_free_preview' => true]);
    }
}
