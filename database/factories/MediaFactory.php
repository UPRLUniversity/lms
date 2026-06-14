<?php

namespace Database\Factories;

use App\Enums\MediaPurpose;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'purpose' => MediaPurpose::Avatars,
            'visibility' => 'public',
            'provider' => 'local',
            'disk' => 'public',
            'path' => 'avatars/'.$this->faker->uuid().'.jpg',
            'public_id' => null,
            'url' => $this->faker->imageUrl(),
            'mime' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(1000, 500000),
            'width' => 256,
            'height' => 256,
            'original_name' => $this->faker->word().'.jpg',
            'uploaded_by' => null,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'purpose' => MediaPurpose::Submissions,
            'visibility' => 'private',
            'disk' => 'private',
            'path' => 'submissions/'.$this->faker->uuid().'.pdf',
            'url' => null,
            'mime' => 'application/pdf',
            'width' => null,
            'height' => null,
            'original_name' => $this->faker->word().'.pdf',
        ]);
    }
}
