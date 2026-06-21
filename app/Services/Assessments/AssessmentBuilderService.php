<?php

namespace App\Services\Assessments;

use App\Enums\AssessmentPlacement;
use App\Enums\AssessmentStatus;
use App\Enums\QuestionDifficulty;
use App\Enums\SelectionMode;
use App\Models\Assessment;
use App\Models\AssessmentPoolRule;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Authoring side of an assessment: create it at a curriculum attachment point, edit its
 * settings, manage its fixed question list or pool rules, report live pool availability,
 * and validate it for publishing.
 */
class AssessmentBuilderService
{
    /**
     * @param  array<string, mixed>  $data  title, placement, module_id, selection_mode, …
     */
    public function createAt(Course $course, array $data, User $author): Assessment
    {
        $placement = $data['placement'] instanceof AssessmentPlacement
            ? $data['placement']
            : AssessmentPlacement::from($data['placement'] ?? AssessmentPlacement::Standalone->value);

        $moduleId = $placement->attachesToModule() ? ($data['module_id'] ?? null) : null;

        return $course->assessments()->create([
            'module_id' => $moduleId,
            'created_by' => $author->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($course, $data['title']),
            'instructions' => $data['instructions'] ?? null,
            'placement' => $placement->value,
            'status' => AssessmentStatus::Draft->value,
            'selection_mode' => $data['selection_mode'] ?? SelectionMode::Fixed->value,
            'position' => $this->nextPosition($course, $moduleId, $placement),
        ]);
    }

    /**
     * Update assessment settings (everything on the settings panel). Slug follows the title
     * only while still a draft, so a published assessment keeps a stable URL.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(Assessment $assessment, array $data): Assessment
    {
        $assessment->fill(array_filter([
            'title' => $data['title'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'passing_score' => $data['passing_score'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? null,
            'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
            'available_from' => $data['available_from'] ?? null,
            'available_until' => $data['available_until'] ?? null,
            'review_policy' => $data['review_policy'] ?? null,
            'selection_mode' => $data['selection_mode'] ?? null,
        ], fn ($v) => $v !== null));

        // Booleans must be set explicitly (array_filter would drop a false).
        foreach (['shuffle_questions', 'shuffle_options', 'show_explanations', 'is_required'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $assessment->{$flag} = (bool) $data[$flag];
            }
        }

        // max_attempts/time_limit can be explicitly cleared back to "unlimited/untimed".
        foreach (['max_attempts', 'time_limit_minutes', 'available_from', 'available_until'] as $nullable) {
            if (array_key_exists($nullable, $data) && ($data[$nullable] === null || $data[$nullable] === '')) {
                $assessment->{$nullable} = null;
            }
        }

        $assessment->save();

        return $assessment;
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed mode
    |--------------------------------------------------------------------------
    */

    /**
     * Replace the fixed question list with the given ordered ids (each optionally carrying a
     * points override). Only questions belonging to the course's bank are accepted.
     *
     * @param  array<int, array{id: int, points_override?: float|null}>  $items
     */
    public function syncFixedQuestions(Assessment $assessment, array $items): void
    {
        $ownIds = Question::where('course_id', $assessment->course_id)->pluck('id')->all();

        $sync = [];
        $position = 0;
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! in_array($id, $ownIds, true)) {
                continue;
            }
            $sync[$id] = [
                'position' => $position++,
                'points_override' => $item['points_override'] ?? null,
            ];
        }

        $assessment->questions()->sync($sync);
    }

    /*
    |--------------------------------------------------------------------------
    | Pooled mode
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $data  category_id, difficulty, count
     */
    public function addPoolRule(Assessment $assessment, array $data): AssessmentPoolRule
    {
        return $assessment->poolRules()->create([
            'category_id' => $data['category_id'],
            'difficulty' => $data['difficulty'] ?? null,
            'count' => max(1, (int) ($data['count'] ?? 1)),
            'position' => (int) ($assessment->poolRules()->max('position') ?? -1) + 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePoolRule(AssessmentPoolRule $rule, array $data): AssessmentPoolRule
    {
        $rule->update([
            'category_id' => $data['category_id'] ?? $rule->category_id,
            'difficulty' => array_key_exists('difficulty', $data) ? $data['difficulty'] : $rule->difficulty,
            'count' => max(1, (int) ($data['count'] ?? $rule->count)),
        ]);

        return $rule;
    }

    public function deletePoolRule(AssessmentPoolRule $rule): void
    {
        $rule->delete();
    }

    /**
     * How many bank questions a pool rule (category + optional difficulty) can actually draw
     * from right now — the number the builder shows as "available in pool".
     */
    public function poolAvailability(int $categoryId, ?QuestionDifficulty $difficulty = null): int
    {
        return Question::query()
            ->where('category_id', $categoryId)
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty->value))
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Publish validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reasons an assessment cannot be published. Empty array ⇒ good to publish.
     *
     * @return array<int, string>
     */
    public function validateForPublish(Assessment $assessment): array
    {
        $errors = [];

        if ($assessment->passing_score < 0 || $assessment->passing_score > 100) {
            $errors[] = 'The passing score must be between 0 and 100.';
        }

        if ($assessment->isPooled()) {
            $rules = $assessment->poolRules()->with('category')->get();

            if ($rules->isEmpty()) {
                $errors[] = 'Add at least one pool rule before publishing.';
            }

            foreach ($rules as $rule) {
                $available = $this->poolAvailability($rule->category_id, $rule->difficulty);
                if ($available < $rule->count) {
                    $label = $rule->category?->name ?? 'a category';
                    $errors[] = "Pool rule for {$label} needs {$rule->count} questions but only {$available} are available.";
                }
            }
        } else {
            if ($assessment->questions()->count() < 1) {
                $errors[] = 'Add at least one question before publishing.';
            }
        }

        if ($assessment->placement->attachesToModule() && $assessment->module_id === null) {
            $errors[] = 'A pre/post-module assessment must be attached to a module.';
        }

        return $errors;
    }

    public function publish(Assessment $assessment): void
    {
        $assessment->forceFill(['status' => AssessmentStatus::Published->value])->save();
    }

    public function unpublish(Assessment $assessment): void
    {
        $assessment->forceFill(['status' => AssessmentStatus::Draft->value])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function uniqueSlug(Course $course, string $title): string
    {
        $base = Str::slug($title) ?: 'assessment';
        $slug = $base;
        $i = 2;

        while ($course->assessments()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function nextPosition(Course $course, ?int $moduleId, AssessmentPlacement $placement): int
    {
        return (int) $course->assessments()
            ->where('module_id', $moduleId)
            ->where('placement', $placement->value)
            ->max('position') + 1;
    }
}
