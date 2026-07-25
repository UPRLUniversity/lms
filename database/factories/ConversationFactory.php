<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type' => ConversationType::Direct,
            'subject' => null,
            'course_id' => null,
            'created_by' => User::factory(),
            'last_message_at' => now(),
        ];
    }

    public function group(?string $subject = null): static
    {
        return $this->state(fn () => [
            'type' => ConversationType::Group,
            'subject' => $subject ?? fake()->sentence(3),
        ]);
    }
}
