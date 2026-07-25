<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ForumPost;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Sent to a thread's author when someone else replies to it, so a question-asker learns
 * an answer arrived without having to poll the forum. Honours the recipient's Section-8
 * channel preferences via UprlNotification.
 */
class ForumReplyNotification extends UprlNotification
{
    public function __construct(public ForumPost $post) {}

    public static function type(): NotificationType
    {
        return NotificationType::ForumReply;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $thread = $this->post->thread;
        $course = $thread->course;

        return (new MailMessage)
            ->subject("New reply · {$thread->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->post->author->name} replied to your discussion in \"{$course->title}\":")
            ->line('"'.$this->preview().'"')
            ->action('View the discussion', route('forum.show', [$course, $thread]))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $thread = $this->post->thread;
        $course = $thread->course;

        return [
            'title' => 'New reply from '.$this->post->author->name,
            'body' => Str::limit($thread->title, 80),
            'url' => route('forum.show', [$course, $thread]),
        ];
    }

    private function preview(): string
    {
        return Str::limit(trim(strip_tags((string) $this->post->body)), 100);
    }
}
