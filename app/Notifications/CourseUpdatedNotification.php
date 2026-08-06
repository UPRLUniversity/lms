<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Your course changed" — sent once per save, summarising everything material that moved,
 * with the instructor's optional note.
 *
 * One notification carrying many changes rather than one per change: an instructor who
 * moves a deadline and makes an item required in the same save has done one thing from
 * the student's point of view.
 */
class CourseUpdatedNotification extends UprlNotification
{
    /**
     * @param  array<int, string>  $summaries
     */
    public function __construct(
        public Course $course,
        public array $summaries,
        public ?string $note = null,
    ) {}

    public static function type(): NotificationType
    {
        return NotificationType::CourseUpdated;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your course was updated · {$this->course->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Some things changed in \"{$this->course->title}\" while you've been working through it:");

        foreach ($this->summaries as $summary) {
            $mail->line('• '.$summary);
        }

        if ($this->note !== null && $this->note !== '') {
            $mail->line('**A note from your instructor:** '.$this->note);
        }

        return $mail
            ->action('Open the course', route('learn.show', $this->course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body' => $this->note !== null && $this->note !== ''
                ? $this->note
                : $this->course->title,
            'url' => route('learn.changes', $this->course),
            'summaries' => $this->summaries,
        ];
    }

    private function headline(): string
    {
        $count = \count($this->summaries);

        return $count === 1
            ? $this->summaries[0]
            : "{$this->course->title} was updated in {$count} ways";
    }
}
