<?php

namespace App\Services\Courses;

use App\Enums\AssessmentPlacement;
use App\Enums\AuditEvent;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place the curriculum's merged order is written and read.
 *
 * A course's outline is a list of BUCKETS. A bucket is a module, or the course-level
 * bucket (module_id = null) that holds standalone assessments and assignments. Inside a
 * bucket, lessons, assessments and assignments share ONE 1..n `position` ladder, so a
 * quiz can sit between lesson 2 and lesson 3 — which three separate ladders could never
 * express (see the 2026_07_31_100100 backfill migration).
 *
 * `AssessmentPlacement` is no longer chosen by the author; it is DERIVED here on every
 * write from where the assessment landed (before the bucket's first lesson → pre-module,
 * after it → post-module, course-level bucket → standalone). Everything that still reads
 * the enum — the insights pairing, the builder badge — keeps working unchanged.
 */
class CurriculumOrderService
{
    /** The item types a bucket can hold. */
    public const TYPES = ['lesson', 'assessment', 'assignment'];

    /**
     * Persist a whole-outline reorder: module order, item order within each bucket, and
     * items moved between buckets — in one transaction.
     *
     * Anything the payload references that doesn't belong to $course is silently skipped
     * rather than rejected, so a crafted payload can't re-home another course's content
     * and a stale tab can't fail loudly over an item someone else just deleted.
     *
     * @param  list<array{module_id: int|null, items?: list<array{type: string, id: int}>}>  $order
     */
    public function apply(Course $course, array $order): void
    {
        $moduleIds = $course->modules()->pluck('id')->all();
        $owned = [
            'lesson' => Lesson::whereIn('module_id', $moduleIds)->pluck('id')->all(),
            'assessment' => $course->assessments()->pluck('id')->all(),
            'assignment' => $course->assignments()->pluck('id')->all(),
        ];

        // Captured before the write, for the audit entry below. A reorder changes the
        // shape of what a student must work through, so it is recorded — and it is
        // recorded HERE because the writes below are bulk query-builder updates, which
        // fire no model events for the audit trait to notice.
        $before = $this->outlineSignature($course);

        DB::transaction(function () use ($order, $moduleIds, $owned) {
            $modulePosition = 0;

            foreach ($order as $row) {
                $moduleId = $row['module_id'] ?? null;

                if ($moduleId !== null) {
                    if (! in_array((int) $moduleId, $moduleIds, true)) {
                        continue;
                    }

                    $moduleId = (int) $moduleId;
                    Module::whereKey($moduleId)->update(['position' => ++$modulePosition]);
                }

                $items = collect($row['items'] ?? [])
                    ->filter(fn ($item) => is_array($item)
                        && in_array($item['type'] ?? null, self::TYPES, true)
                        && in_array((int) ($item['id'] ?? 0), $owned[$item['type']], true))
                    // A lesson always lives in a module — it has nowhere to sit in the
                    // course-level bucket, so drop it rather than orphan it.
                    ->reject(fn (array $item) => $moduleId === null && $item['type'] === 'lesson')
                    ->values();

                $this->write($items, $moduleId);
            }
        });

        $this->recordReorder($course, $before);
    }

    /**
     * Record the reorder, if it actually moved anything.
     *
     * The diff is the readable outline — "Module A: Lesson 3, Quiz 1" — rather than a
     * wall of position integers, because the question an administrator brings to the
     * audit log is "what did the course look like before?", not "which row got which
     * number".
     *
     * @param  array<string, string>  $before
     */
    private function recordReorder(Course $course, array $before): void
    {
        $after = $this->outlineSignature($course);

        if ($before === $after) {
            return;   // a drag that ended where it started is not an event
        }

        app(AuditLogger::class)->recordChange(
            AuditEvent::CurriculumReordered,
            $course,
            $before,
            $after,
            [],
            "Curriculum reordered — {$course->title}",
        );
    }

    /**
     * A human-readable snapshot of the merged outline: bucket label => ordered items.
     *
     * Titles are resolved up front into one lookup per type — bucketRows() carries only
     * ids, and a per-row query here would mean an N+1 on every single reorder.
     *
     * @return array<string, string>
     */
    private function outlineSignature(Course $course): array
    {
        $moduleIds = $course->modules()->pluck('id')->all();

        $titles = [
            'lesson' => Lesson::whereIn('module_id', $moduleIds)->pluck('title', 'id')->all(),
            'assessment' => $course->assessments()->pluck('title', 'id')->all(),
            'assignment' => $course->assignments()->pluck('title', 'id')->all(),
        ];

        $buckets = $course->modules()->orderBy('position')->get(['id', 'title'])
            ->map(fn (Module $module) => ['id' => $module->id, 'label' => $module->title])
            ->push(['id' => null, 'label' => 'Course level']);

        $signature = [];

        foreach ($buckets as $bucket) {
            $items = $this->bucketRows($course, $bucket['id'])
                ->map(fn (array $row) => $titles[$row['type']][$row['id']] ?? Str::headline($row['type'])." #{$row['id']}")
                ->all();

            if ($items !== []) {
                $signature[$bucket['label']] = implode(' → ', $items);
            }
        }

        return $signature;
    }

    /**
     * Place a just-created item at $index within its bucket (0-based), pushing whatever
     * was there down. A null $index appends. Used by the "insert here" affordances so a
     * new lesson/quiz/assignment lands where the author clicked, not at the end.
     */
    public function insertAt(Course $course, ?int $moduleId, string $type, int $id, ?int $index): void
    {
        if ($index === null) {
            return;
        }

        DB::transaction(function () use ($course, $moduleId, $type, $id, $index) {
            $items = $this->bucketRows($course, $moduleId)
                ->reject(fn (array $row) => $row['type'] === $type && $row['id'] === $id)
                ->values()
                ->map(fn (array $row) => ['type' => $row['type'], 'id' => $row['id']]);

            $items->splice(max(0, min($index, $items->count())), 0, [['type' => $type, 'id' => $id]]);

            $this->write($items, $moduleId);
        });
    }

    /**
     * Renumber every bucket of a course into the merged ladder, placing each item where
     * the pre-merge layout would have rendered it: pre-module quizzes, then lessons, then
     * post-module quizzes, then assignments.
     *
     * Seeders (and any import) build those three groups independently and each start
     * their own count from 1, which would leave a lesson and a quiz claiming the same
     * slot. One call at the end settles the whole course — the same thing the
     * 2026_07_31_100100 migration did once for existing data.
     */
    public function normalise(Course $course): void
    {
        $course->load(['modules.lessons', 'modules.assessments', 'modules.assignments', 'assessments', 'assignments']);

        DB::transaction(function () use ($course) {
            foreach ($course->modules->sortBy('position') as $module) {
                $byPlacement = fn (AssessmentPlacement $p) => $module->assessments
                    ->where('placement', $p)->sortBy('position')
                    ->map(fn (Assessment $a) => ['type' => 'assessment', 'id' => $a->id]);

                $this->write(
                    $byPlacement(AssessmentPlacement::PreModule)
                        ->concat($module->lessons->sortBy('position')->map(fn (Lesson $l) => ['type' => 'lesson', 'id' => $l->id]))
                        ->concat($byPlacement(AssessmentPlacement::PostModule))
                        ->concat($byPlacement(AssessmentPlacement::Standalone))
                        ->concat($module->assignments->sortBy('position')->map(fn (Assignment $a) => ['type' => 'assignment', 'id' => $a->id]))
                        ->values(),
                    $module->id,
                );
            }

            $this->write(
                $course->assessments->whereNull('module_id')->sortBy('position')
                    ->map(fn (Assessment $a) => ['type' => 'assessment', 'id' => $a->id])
                    ->concat($course->assignments->whereNull('module_id')->sortBy('position')
                        ->map(fn (Assignment $a) => ['type' => 'assignment', 'id' => $a->id]))
                    ->values(),
                null,
            );
        });
    }

    /**
     * The next free position at the end of a bucket — what a plain "add" uses.
     */
    public function nextPosition(Course $course, ?int $moduleId): int
    {
        $max = 0;

        if ($moduleId !== null) {
            $max = max($max, (int) Lesson::where('module_id', $moduleId)->max('position'));
        }

        foreach ([$course->assessments(), $course->assignments()] as $query) {
            $max = max($max, (int) $this->scopeToBucket($query, $moduleId)->max('position'));
        }

        return $max + 1;
    }

    /**
     * Merge three already-loaded collections into the one order the bucket renders in.
     * Both the builder outline and the student player go through here, so an author and
     * a learner can never see a different sequence.
     *
     * @param  iterable<Lesson>  $lessons
     * @param  iterable<Assessment>  $assessments
     * @param  iterable<Assignment>  $assignments
     * @return Collection<int, array{type: string, model: Lesson|Assessment|Assignment}>
     */
    public function merge(iterable $lessons, iterable $assessments, iterable $assignments): Collection
    {
        return collect($lessons)->map(fn (Lesson $m) => ['type' => 'lesson', 'model' => $m])
            ->concat(collect($assessments)->map(fn (Assessment $m) => ['type' => 'assessment', 'model' => $m]))
            ->concat(collect($assignments)->map(fn (Assignment $m) => ['type' => 'assignment', 'model' => $m]))
            // Position first; type rank then id only break ties, so the order is stable
            // and identical on every render even before a bucket has been reordered once.
            ->sortBy(fn (array $row) => $this->sortKey(
                (int) $row['model']->position,
                $row['type'],
                (int) $row['model']->id,
            ))
            ->values();
    }

    /**
     * Write one bucket's ladder: consecutive positions from 1, each item re-homed to the
     * bucket, and assessment placement re-derived from where it landed.
     *
     * @param  Collection<int, array{type: string, id: int}>  $items
     */
    private function write(Collection $items, ?int $moduleId): void
    {
        $firstLessonIndex = $items->search(fn (array $item) => $item['type'] === 'lesson');
        $alreadyPre = $this->preModuleIds($items, $moduleId, $firstLessonIndex);

        foreach ($items as $index => $item) {
            $position = $index + 1;

            match ($item['type']) {
                'lesson' => Lesson::whereKey($item['id'])->update([
                    'module_id' => $moduleId,
                    'position' => $position,
                ]),
                'assessment' => Assessment::whereKey($item['id'])->update([
                    'module_id' => $moduleId,
                    'position' => $position,
                    'placement' => $this->placementFor($moduleId, $index, $firstLessonIndex, $alreadyPre, $item['id'])->value,
                ]),
                'assignment' => Assignment::whereKey($item['id'])->update([
                    'module_id' => $moduleId,
                    'position' => $position,
                ]),
            };
        }
    }

    /**
     * Where an assessment sits, derived from the bucket it landed in and whether it comes
     * before that bucket's first lesson.
     *
     * A module with NO lessons is the one case position can't answer: nothing exists to
     * be before or after. There we leave an existing pre-module choice alone rather than
     * flipping the author's stated intent — the derivation takes over the moment a lesson
     * arrives and the question becomes answerable.
     *
     * @param  list<int>  $alreadyPre
     */
    private function placementFor(?int $moduleId, int $index, int|false $firstLessonIndex, array $alreadyPre, int $id): AssessmentPlacement
    {
        if ($moduleId === null) {
            return AssessmentPlacement::Standalone;
        }

        if ($firstLessonIndex === false) {
            return in_array($id, $alreadyPre, true)
                ? AssessmentPlacement::PreModule
                : AssessmentPlacement::PostModule;
        }

        return $index < $firstLessonIndex
            ? AssessmentPlacement::PreModule
            : AssessmentPlacement::PostModule;
    }

    /**
     * Assessments in a lessonless module bucket that are already marked pre-module — one
     * query, and only in the case where placement cannot be derived from position.
     *
     * @param  Collection<int, array{type: string, id: int}>  $items
     * @return list<int>
     */
    private function preModuleIds(Collection $items, ?int $moduleId, int|false $firstLessonIndex): array
    {
        if ($moduleId === null || $firstLessonIndex !== false) {
            return [];
        }

        $ids = $items->where('type', 'assessment')->pluck('id');

        return $ids->isEmpty() ? [] : Assessment::whereIn('id', $ids)
            ->where('placement', AssessmentPlacement::PreModule->value)
            ->pluck('id')->all();
    }

    /**
     * One bucket's current contents, ordered — read fresh from the database because
     * insertAt runs right after a create.
     *
     * @return Collection<int, array{type: string, id: int, position: int}>
     */
    private function bucketRows(Course $course, ?int $moduleId): Collection
    {
        $lessons = $moduleId === null
            ? collect()
            : Lesson::where('module_id', $moduleId)->orderBy('position')->orderBy('id')
                ->get(['id', 'position'])
                ->map(fn (Lesson $l) => ['type' => 'lesson', 'id' => $l->id, 'position' => (int) $l->position]);

        $assessments = $this->scopeToBucket($course->assessments(), $moduleId)
            ->orderBy('position')->orderBy('id')->get(['id', 'position'])
            ->map(fn (Assessment $a) => ['type' => 'assessment', 'id' => $a->id, 'position' => (int) $a->position]);

        $assignments = $this->scopeToBucket($course->assignments(), $moduleId)
            ->orderBy('position')->orderBy('id')->get(['id', 'position'])
            ->map(fn (Assignment $a) => ['type' => 'assignment', 'id' => $a->id, 'position' => (int) $a->position]);

        return $lessons->concat($assessments)->concat($assignments)
            ->sortBy(fn (array $row) => $this->sortKey($row['position'], $row['type'], $row['id']))
            ->values();
    }

    /**
     * One sortable string per row: position, then type rank, then id. A single composite
     * key rather than a multi-column sort because Collection::sortBy() treats closures in
     * an array as COMPARATORS, not value extractors — a silent, order-scrambling trap.
     */
    private function sortKey(int $position, string $type, int $id): string
    {
        return sprintf('%08d-%d-%08d', $position, array_search($type, self::TYPES, true), $id);
    }

    /**
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    private function scopeToBucket($query, ?int $moduleId)
    {
        return $moduleId === null
            ? $query->whereNull('module_id')
            : $query->where('module_id', $moduleId);
    }
}
