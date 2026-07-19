<?php

namespace App\Services\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Authoring side of an assignment: create it at a curriculum attachment point (after a
 * module's lessons, or standalone), edit its settings, attach a rubric and validate it
 * for publishing. The publish gate keeps every published assignment gradebook-safe:
 * max_points is required and must be > 0 (Section 6.5 divides by it).
 */
class AssignmentBuilderService
{
    /**
     * @param  array<string, mixed>  $data  title, module_id, type, …
     */
    public function createAt(Course $course, array $data, User $author): Assignment
    {
        $moduleId = $data['module_id'] ?? null;

        return $course->assignments()->create([
            'module_id' => $moduleId,
            'created_by' => $author->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($course, $data['title']),
            // Explicit default: an omitted key must not write NULL into the NOT NULL column.
            'type' => $data['type'] ?? AssignmentType::Either->value,
            'status' => AssignmentStatus::Draft->value,
            'position' => (int) $course->assignments()->where('module_id', $moduleId)->max('position') + 1,
        ]);
    }

    /**
     * Update settings from the builder form. Slug follows the title only while still a
     * draft, so a published assignment keeps a stable URL.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(Assignment $assignment, array $data): Assignment
    {
        $assignment->fill(array_filter([
            'title' => $data['title'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'type' => $data['type'] ?? null,
            'max_points' => $data['max_points'] ?? null,
        ], fn ($v) => $v !== null));

        if (! $assignment->isPublished() && isset($data['title'])) {
            $assignment->slug = $this->uniqueSlug($assignment->course, $data['title'], $assignment->id);
        }

        // Booleans explicitly (array_filter would drop a false).
        foreach (['allow_late', 'is_required'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $assignment->{$flag} = (bool) $data[$flag];
            }
        }

        // Nullable fields can be explicitly cleared.
        foreach (['due_at', 'rubric_id', 'module_id'] as $nullable) {
            if (array_key_exists($nullable, $data)) {
                $assignment->{$nullable} = ($data[$nullable] === null || $data[$nullable] === '') ? null : $data[$nullable];
            }
        }

        $assignment->save();

        return $assignment;
    }

    /**
     * Reasons an assignment cannot be published. Empty array ⇒ good to publish.
     *
     * @return array<int, string>
     */
    public function validateForPublish(Assignment $assignment): array
    {
        $errors = [];

        if ($assignment->max_points === null || (float) $assignment->max_points <= 0) {
            $errors[] = 'Set the points this assignment is graded out of (more than zero) before publishing — the course gradebook needs it.';
        }

        if (trim(strip_tags((string) $assignment->instructions)) === '') {
            $errors[] = 'Write instructions so students know what to hand in.';
        }

        return $errors;
    }

    public function publish(Assignment $assignment): void
    {
        $assignment->forceFill(['status' => AssignmentStatus::Published->value])->save();
    }

    public function unpublish(Assignment $assignment): void
    {
        $assignment->forceFill(['status' => AssignmentStatus::Draft->value])->save();
    }

    private function uniqueSlug(Course $course, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'assignment';
        $slug = $base;
        $i = 2;

        while ($course->assignments()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
