<?php

namespace App\Services\Courses;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Support\Curriculum\CurriculumChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Decides whether an edit is worth telling students about, and says why in their words.
 *
 * The rule, in one line: a change is MATERIAL when it moves a deadline, changes what is
 * graded, or changes what a learner can reach. Everything else — wording, media,
 * presentation — is cosmetic, recorded for staff but never announced.
 *
 * Titles are the one attribute that changes category by context: renaming a lesson is
 * cosmetic, but renaming an assessment or assignment is material, because that name ends
 * up on a transcript the student has to recognise later.
 *
 * Call it BEFORE save, while the model is still dirty.
 */
class CurriculumChangeClassifier
{
    /**
     * Attributes whose movement a student should hear about, per type.
     *
     * @var array<class-string, array<int, string>>
     */
    private const MATERIAL = [
        Assessment::class => [
            'title', 'status', 'is_required', 'counts_toward_grade',
            'passing_score', 'max_attempts', 'time_limit_minutes',
            'available_from', 'available_until', 'hidden_at',
        ],
        Assignment::class => [
            'title', 'status', 'is_required', 'counts_toward_grade',
            'max_points', 'due_at', 'allow_late', 'hidden_at',
        ],
        Lesson::class => ['hidden_at'],
        Module::class => ['hidden_at'],
        Course::class => ['is_sequential'],
    ];

    /**
     * How each attribute reads in a sentence.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'due_at' => 'due date',
        'available_from' => 'opening date',
        'available_until' => 'closing date',
        'max_points' => 'total points',
        'passing_score' => 'pass mark',
        'max_attempts' => 'attempt limit',
        'time_limit_minutes' => 'time limit',
        'is_required' => 'required status',
        'counts_toward_grade' => 'grade weighting',
        'allow_late' => 'late submission policy',
        'is_sequential' => 'lesson unlocking',
        'status' => 'published status',
        'title' => 'title',
        'content_text' => 'content',
        'instructions' => 'instructions',
        'description' => 'description',
        'video_url' => 'video',
        'external_url' => 'link',
    ];

    /**
     * Describe every change pending on a dirty model.
     *
     * @return array<int, CurriculumChange>
     */
    public function classify(Model $item): array
    {
        $material = self::MATERIAL[$item::class] ?? [];
        $name = $this->name($item);
        $changes = [];

        foreach ($item->getDirty() as $attribute => $new) {
            $old = $item->getOriginal($attribute);

            if ($this->unchanged($old, $new)) {
                continue;
            }

            if ($attribute === 'hidden_at') {
                $changes[] = $new === null
                    ? CurriculumChange::material('restored', "{$name} is available again.", $item)
                    : CurriculumChange::material('hidden', "{$name} has been withdrawn from the course.", $item);

                continue;
            }

            $changes[] = \in_array($attribute, $material, true)
                ? CurriculumChange::material(
                    'updated',
                    $this->sentence($name, $attribute, $old, $new),
                    $item,
                )
                // Verbless on purpose — "instructions updated" and "title updated" both
                // read correctly, where "was edited" only agrees with singular labels.
                : CurriculumChange::cosmetic(
                    'updated',
                    "{$name}: ".$this->label($attribute).' updated.',
                    $item,
                );
        }

        return $changes;
    }

    /**
     * A newly added item — material whenever students can already see it, since it is new
     * work appearing in a course they are partway through.
     */
    public function forCreated(Model $item): CurriculumChange
    {
        $name = $this->name($item);
        $type = $this->type($item);

        return CurriculumChange::material('created', "A new {$type} was added: {$name}.", $item);
    }

    /**
     * A reorder changes what unlocks next under sequential access, so it is material even
     * though no item's own attributes moved.
     */
    public function forReorder(Course $course): CurriculumChange
    {
        return $course->isSequential()
            ? CurriculumChange::material('reordered', 'The course order changed, so what unlocks next may have moved.', $course)
            : CurriculumChange::cosmetic('reordered', 'The course order changed.', $course);
    }

    /**
     * Does this set of changes warrant telling students?
     *
     * @param  array<int, CurriculumChange>  $changes
     */
    public function anyMaterial(array $changes): bool
    {
        foreach ($changes as $change) {
            if ($change->isMaterial()) {
                return true;
            }
        }

        return false;
    }

    private function sentence(string $name, string $attribute, mixed $old, mixed $new): string
    {
        $label = $this->label($attribute);

        // Booleans read as a state, not as "false → true".
        if (\is_bool($old) || \is_bool($new) || \in_array($attribute, ['is_required', 'counts_toward_grade', 'allow_late', 'is_sequential'], true)) {
            return $new
                ? "{$name}: {$label} is now on."
                : "{$name}: {$label} is now off.";
        }

        $from = $this->readable($old);
        $to = $this->readable($new);

        if ($from === null) {
            return "{$name}: {$label} was set to {$to}.";
        }

        return "{$name}: {$label} changed from {$from} to {$to}.";
    }

    private function readable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('j M Y, g:ia');
        }

        // Datetime columns arrive as strings from getOriginal().
        if (\is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}[ T]/', $value) === 1) {
            return Carbon::parse($value)->format('j M Y, g:ia');
        }

        return (string) $value;
    }

    private function label(string $attribute): string
    {
        return self::LABELS[$attribute] ?? str_replace('_', ' ', $attribute);
    }

    /**
     * Loose comparison on purpose: a form round-trip turns 20 into "20" and a Carbon into
     * a string, and neither is a change a student should hear about.
     */
    private function unchanged(mixed $old, mixed $new): bool
    {
        if ($old === null && $new === null) {
            return true;
        }

        if ($old instanceof \DateTimeInterface || $new instanceof \DateTimeInterface) {
            return $this->readable($old) === $this->readable($new);
        }

        if (\is_scalar($old) && \is_scalar($new)) {
            return (string) $old === (string) $new;
        }

        return false;
    }

    private function name(Model $item): string
    {
        $title = $item->getAttribute('title');

        return \is_string($title) && $title !== '' ? "“{$title}”" : 'An item';
    }

    private function type(Model $item): string
    {
        return match (true) {
            $item instanceof Lesson => 'lesson',
            $item instanceof Assessment => 'assessment',
            $item instanceof Assignment => 'assignment',
            $item instanceof Module => 'module',
            default => 'item',
        };
    }
}
